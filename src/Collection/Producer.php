<?hh // strict
namespace HHReactor\Collection;
use HHReactor\Asio\ResettableConditionWaitHandle;
use HHReactor\Wrapper;
use HHReactor\TotalAwaitable;
<<__ConsistentConstruct>>
// MUST CLONE TO SEPARATE POINTERS
class Producer<+T> implements AsyncIterator<T> {
	private LinkedList<?(mixed, T)> $lag;
	private AsyncIteratorWrapper<T> $iterator;
	private ?Haltable<?(mixed, T)> $haltable = null;
	private bool $finished = false;
	public function __construct(AsyncIterator<T> $raw_iterator) {
		$this->iterator = new AsyncIteratorWrapper($raw_iterator);
		$this->lag = new LinkedList();
	}
	public function __clone(): void {
		$this->lag = clone $this->lag;
	}
	public async function next(): Awaitable<?(mixed, T)> {
		if($this->lag->is_empty()) {
			// we're on the cutting edge, await next
			$next = $this->iterator->next();
			$this->haltable = new Haltable($next);
			$ret = await $this->haltable;
			if($this->lag->is_empty()) {
				$this->lag->add($ret); // broadcast to other producers (including this one)
			}
			// var_dump($this->iterator);
			// var_dump($ret);
			// we might not be the first to resolve, check first:
		}
		return $this->lag->shift(); // this is guaranteed by the above to never be empty
	}
	public async function halt(?\Exception $e = null): Awaitable<void> {
		$haltable = $this->haltable;
		invariant(!is_null($haltable), 'Attempted to halt producer before starting iteration.');
		await $haltable->halt($e);
	}
	// public function isFinished(): bool {
	// 	return $this->lag->is_empty() && 
	// }
	public function fast_forward(): Iterator<T> {
		// no risk of "Generator already started" or "Changed during iteration" exceptions, because there are no underlying core Hack collections in LinkedList iterables
		while(!$this->lag->is_empty()) {
			$next = $this->lag->shift();
			if(!is_null($next))
				yield $next[1];
		}
	}
	
	// ReactiveX-ish operators
	public function map<Tv>((function(T): Tv) $f): Producer<Tv> {
		return new self(async {
			foreach(clone $this await as $v)
				yield $f($v);
		});
	}
	public function scan<Tv super T>((function(T, T): Tv) $f): Producer<Tv> {
		$last = null;
		return new static(async {
			foreach(clone $this await as $v) {
				if(!is_null($last))
					yield $f($last, $v);
				else
					yield $v;
				
				$last = $v;
			}
		});
	}
	public async function last(): Awaitable<?T> {
		$v = null;
		foreach(clone $this await as $v) {}
		return $v;
	}
	public function reduce<Tv super T>((function(T, T): Tv) $f): Awaitable<?Tv> {
		return $this->scan($f)->last();
	}
	private function flat_map<Tv>((function(T): Producer<Tv>) $f): Producer<Tv> {
		// this would kill it as an anonymous class, but alas not yet
		$race_handle = new ResettableConditionWaitHandle();
		$cloned = clone $this;
		$total_awaitable = null;
		$emitter = async {
			await \HH\Asio\later(); // guarantee that the race handle is set
			$pending_producers = new TotalAwaitable(Vector{ $cloned->next() });
			foreach($cloned await as $core_v) {
				$producer = clone $f($core_v); // should i clone defensively?
				foreach($producer->fast_forward() as $v) {
					await $race_handle->succeed($v); // note: if another element is resolved while dealing with this ready-waited result, then it could very well take precedence: this is not guaranteed to resolve consecutively.
				}
				invariant(!is_null($total_awaitable), 'Can\'t be null because it\'s set right after this async block');
				await $total_awaitable->add(async {
					foreach($producer await as $v)
						await $race_handle->succeed($v);
				});
			}
		};
		$total_awaitable = new TotalAwaitable(Vector{ $emitter });
		$race_handle->set(() ==> $total_awaitable);
		return new static(static::_listen_produce($race_handle, $total_awaitable));
	}
	public function group_by<Tk as arraykey>((function(T): Tk) $keysmith): Producer<this> {
		$handles = Map{};
		$total_wait_handle = null;
		$total_iterator = async {
			await \HH\Asio\later(); // guarantee `$total_wait_handle` is set
			foreach(clone $this await as $v) {
				$key = $keysmith($v);
				if(!$handles->containsKey($key)) {
					invariant(!is_null($total_wait_handle), 'Impossible, because it\'s set just after this async block.');
					$handle = new ResettableConditionWaitHandle($total_wait_handle);
					// $total_awaitable->add($iterator);
					$handles[$key] = $handle;
					yield new static(static::_listen_produce($handle, $total_wait_handle));
				}
				await $handles[$key]->succeed($v);
			}
		};
		$total_wait_handle = async {
			foreach($total_iterator await as $_) {}
		};
		return new static($total_iterator);
	}
	public function buffer(Producer<mixed> $signal): Producer<\ConstVector<T>> {
		return new self(async {
			$clone = clone $this;
			foreach(clone $signal await as $_)
				yield new Vector($clone->fast_forward());
		});
	}
	
	// Hmm, tricky. Defer.
	// public function debounce(int $usecs): this {
	// 	$wait_handle = new ResettableConditionWaitHandle(async () ==> {
	// 		await \HH\Asio\usleep($usecs);
	// 		return $v;
	// 	});
	// 	foreach(clone $this await as $v) {
	// 		if(!is_null($wait_handle) && !$wait_handle->isFinished())
	// 			await $wait_handle->fail(new \HHReactor\ResetException());
	// 	}
	// }
	
	public function window(Producer<mixed> $signal): Producer<this> {
		$handle = new Wrapper(new ResettableConditionWaitHandle());
		$value_emitter = async {
			$cloned = clone $this; // crucial this happens before later() because we must wait for `$signal` to tick once before later() resolves
			await \HH\Asio\later(); // wait for handle to be guaranteed-set
			foreach($cloned await as $v) {
				await $handle->get()->succeed($v);
			}
		};
		$producer_emitter = async {
			foreach(clone $signal await as $_) {
				$handle->set(new ResettableConditionWaitHandle(() ==> $value_emitter));
				yield new static(static::_listen_produce($handle->get(), $value_emitter));
			}
		};
		$handle->get()->set(() ==> $value_emitter);
		return new static($producer_emitter);
	}
	
	public async function collapse(): Awaitable<\ConstVector<T>> {
		$accumulator = Vector{};
		foreach(clone $this await as $v)
			$accumulator->add($v);
		return $accumulator;
	}
	
	public final static function defer((function(): Producer<T>) $factory): this {
		return new static(new DeferredProducer($factory));
	}
	public static function empty(): this {
		return new static(new EmptyAsyncIterator());
	}
	public static function throw(\Exception $e): this {
		return new static(async {
			throw $e;
		});
	}
	public static function interval(int $usecs): Producer<int> {
		return new static(async {
			for($i = 0; ; $i++) {
				await \HH\Asio\usleep($usecs);
				yield $i;
			}
		});
	}
	public static final function just(T $v): this {
		return new static(async {
			yield $v;
		});
	}
	public static function range(int $n, int $m): Producer<int> {
		return new static(async {
			for(; $n < $m; $n++)
				yield $n;
		});
	}
	public static final function repeat(T $v, ?int $n = null): this {
		return new static(async {
			for(; is_null($n) || $n > 0; !is_null($n) && $n--)
				yield $v;
		});
	}
	public static final function repeat_sequence(Traversable<T> $vs, ?int $n = null): this {
		return new static(async {
			for(; is_null($n) || $n > 0; !is_null($n) && $n--)
				foreach($vs as $v)
					yield $v;
		});
	}
	public final static function timer(T $v, int $delay): this {
		return new static(async {
			await \HH\Asio\usleep($delay);
			yield $v;
		});
	}
	public static final function from_nonblocking(Iterable<Awaitable<T>> $subawaitables): this {
		return new static(self::_from_nonblocking($subawaitables));
	}
	public static final function _from_nonblocking(Iterable<Awaitable<T>> $subawaitables): AsyncIterator<T> {
		$race_handle = new ResettableConditionWaitHandle(); // ?Wrapper<ConditionWaitHandle>
		$pending_subawaitables = 
			$subawaitables->filter((Awaitable<T> $v) ==> !$v->getWaitHandle()->isFinished())
			              ->map(async (Awaitable<T> $v) ==> {
			              		try {
				              		$resolved_v = await $v;
				              		await $race_handle->succeed($resolved_v);
				              	}
				              	catch(\Exception $e) {
				              		await $race_handle->fail($e);
				              	}
			              	});
		if(!is_null($pending_subawaitables->firstValue())) {
			$total_awaitable = async {
				await \HH\Asio\v($pending_subawaitables);
			};
			$race_handle->set(() ==> $total_awaitable->getWaitHandle());
			return self::_listen_produce($race_handle, $total_awaitable);
		}
		else {
			return async {
				foreach($subawaitables as $v)
					yield $v->getWaitHandle()->result();
			};
		}
	}
	public final static function from(Iterable<Awaitable<T>> $subawaitables): this {
		return new static(async {
			foreach($subawaitables as $v) {
				$v = await $v;
				yield $v;
			}
		});
	}
	public final static function merge(Traversable<this> $producers): this {
		return new static(self::_merge($producers));
	}
	private final static async function _merge(Traversable<this> $producers): AsyncIterator<T> {
		$race_handle = new ResettableConditionWaitHandle();
		$pending_producers = Vector{};
		$total_awaitable = null;
		foreach($producers as $producer) {
			foreach($producer->fast_forward() as $v)
				yield $v; // this is not a trivial procedure: what if this Iterator is instantiated outside of a `HH\Asio\join`, `next`ed, then control is handed back to the join? 
			// if(!$producer->isFinished()) {}
			// [OBSOLETE] // vital that they aren't finished, so that these notifiers won't try to notify the race_handle before we get a chance to `set` it just afterwards
			
			$pending_producers->add(async {
				await \HH\Asio\later(); // even if this producer is totally resolved, defer until we reach the top join again. This is so that race handle can be guaranteed to be primed.
				try {
					// foreach($producer await as $v) {
					foreach($producer await as $next) {
						await $race_handle->succeed($next);
					}
				}
				catch(\Exception $e) {
					await $race_handle->fail($e);
				}
			});
			$total_awaitable = async {
				await \HH\Asio\v($pending_producers);
			};
			$race_handle->set(() ==> $total_awaitable->getWaitHandle());
		}
		if(!is_null($total_awaitable))
			foreach(static::_listen_produce($race_handle, $total_awaitable) await as $v)
				yield $v;
	}
	protected static async function _listen_produce<Tv>(ResettableConditionWaitHandle<Tv> $race_handle, Awaitable<mixed> $total_awaitable): AsyncIterator<Tv> {
		while(true) {
			try {
				$v = await $race_handle;
				// reset *must* precede yield
				
				yield $v;
			}
			catch(\InvalidArgumentException $e) {
				$total_wait_handle = $total_awaitable->getWaitHandle();
				invariant(!is_null($total_wait_handle), 'Race handle must not be based on ready-wait handle to emit-produce.'); // this might be impossible to reach
				if(!$total_wait_handle->isFinished() || $total_wait_handle->isFailed())
					// Did one of the producers fail, or was the race handle `fail`ed explicitly? If so, rethrow
					throw $e;
				else
					// else, we assume the exception occurs simply because of the logic of the last arc of iteration
					return;
			}
		}
	}
}