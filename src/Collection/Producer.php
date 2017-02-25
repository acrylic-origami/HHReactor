<?hh // strict
namespace HHReactor\Collection;
use HHReactor\Asio\{
	ResettableConditionWaitHandle,
	Haltable,
	Extendable,
	ExtendableLifetime,
	HaltResult
};
use HHReactor\Wrapper;
<<__ConsistentConstruct>>
// MUST CLONE TO SEPARATE POINTERS
class Producer<+T> implements AsyncIterator<T> {
	private Queue<?(mixed, T)> $lag;
	private AsyncIteratorWrapper<T> $iterator;
	private ?Haltable<?(mixed, HaltResult<T>)> $haltable = null;
	private bool $finished = false;
	public function __construct(AsyncIterator<T> $raw_iterator) {
		$this->iterator = new AsyncIteratorWrapper($raw_iterator);
		$this->lag = new Queue();
	}
	public function __clone(): void {
		$this->lag = clone $this->lag;
	}
	public async function next(): Awaitable<?(mixed, T)> {
		if($this->lag->is_empty()) {
			// we're on the cutting edge, await next
			$next = $this->iterator->next();
			$this->haltable = new Haltable($next);
			$ret = await $this->haltable; // may have halted, but only locally
			if($this->lag->is_empty()) {
				$next_result = $ret['result'];
				if(is_null($next_result))
					return null; // whether or not halted, $next_result null signals end of iteration
				elseif(!$next_result[1]['_halted']) { // the more global, underlying AsyncIterator may have halted
					/* HH_IGNORE_ERROR[4110] Because it's not halted, it's either null because our `T` is nullable, or it is not null. */
					$this->lag->add(tuple($next_result[0], $next_result[1]['result'])); // broadcast to other producers (including this one)
				}
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
	public async function get_lifetime(): Awaitable<void> {
		foreach(clone $this await as $_) {}
	}
	public async function collapse(): Awaitable<\ConstVector<T>> {
		$accumulator = Vector{};
		foreach(clone $this await as $v)
			$accumulator->add($v);
		return $accumulator;
	}
	public function enumerate(): Producer<(int, T)> {
		return new static(async {
			$counter = 0;
			foreach(clone $this await as $v) {
				yield tuple($counter++, $v);
			}
		});
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
	public async function first(): Awaitable<?T> {
		foreach(clone $this await as $v) {
			return $v;
		}
		return null;
	}
	public function reduce<Tv super T>((function(T, T): Tv) $f): Awaitable<?Tv> {
		return $this->scan($f)->last();
	}
	public function flat_map<Tv>((function(T): Producer<Tv>) $meta): Producer<Tv> {
		$clone = clone $this;
		$subject = new Subject(Vector{
			async (Subject<Tv> $subject) ==> {
				foreach($clone await as $seed) {
					$subclone = clone $meta($seed);
					$subject->attach(async (Subject<Tv> $subject) ==> {
						foreach($subclone await as $v)
							$subject->emit($v);
					});
				}
			}
		});
		return new static($subject);
	}
	public function switch<Tv>((function(T): Producer<Tv>) $meta): Producer<Tv> {
		$clone = clone $this;
		$subject = new Subject(Vector{
			async (Subject<Tv> $subject) ==> {
				$current_idx = new Wrapper(-1);
				foreach($clone await as $seed) {
					$i = $current_idx->get();
					$current_idx->set(++$i);
					
					$subclone = clone $meta($seed);
					$subject->attach(async (Subject<Tv> $subject) ==> {
						foreach($subclone await as $v)
							if($current_idx->get() === $i) // eh, not the most performant way... but meh, until it becomes a problem
								$subject->emit($v);
					});
				}
			}
		});
		return new static($subject);
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
	
	public function debounce(int $usecs): this {
		$clone = clone $this;
		$extendable = new Extendable(async {
			$v = await $clone->next();
			return Vector{ $v };
		}); 
		$delay_return = async (?(mixed, T) $v) ==> {
			await \HH\Asio\usleep($usecs);
			return $v;
		};
		return new static(new EmitIterator(Vector{
			async (EmitTrigger<T> $trigger) ==> {
				// await $extendable; // race condition anywhere?
				$lifetime = $clone->get_lifetime();
				while(!\HH\Asio\has_finished($lifetime)) {
					$V = await $extendable;
					$last = $V->lastValue();
					invariant(!is_null($last), 'Can\'t be `null`: `Extendable` initialized with at least one value.');
					$trigger($last[1]); // `$last[1]` is the value of the key-value pair returned by $clone->next()
				}
			}
		}, async (Vector<Awaitable<mixed>> $total) ==> {
			$first = await $extendable;
			$extendable->soft_extend($delay_return($first->lastValue()));
			await \HH\Asio\v($total->concat(Vector{
				async {
					do {
						$v = await $clone->next();
						$extendable->soft_extend($delay_return($v));
					}
					while(!is_null($v));
				}
			}));
		}));
	}
	
	public function window(Producer<mixed> $signal): Producer<this> {
		return new static(async {
			$clone = clone $this;
			foreach($signal await as $_)
				yield new static(
					new EmitIterator(
						Vector{
							async (EmitTrigger<T> $trigger) ==> {
								foreach($clone await as $v)
									$trigger($v);
							}
						},
						(Vector<Awaitable<mixed>> $total) ==>
							Producer::from_nonblocking($total->concat(Vector{ $signal->next() }))
					              ->first() // race the next signal tick and the bulk emitter total
            	)
				);
		});
	}
	
	public function sample(Producer<mixed> $signal): Producer<?T> {
		return new static(async {
			foreach($this->window($signal) await as $producer) {
				$v = await $producer->last();
				yield $v;
			}
		});
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
	public function zip<Tu, Tv, Tx>(Producer<Tu> $A, Producer<Tv> $B, (function(Tu, Tv): Tx) $combinator): Producer<Tx> {
		return new self(async {
			while(true) {
				// requires HHVM ^3.16
				list($u, $v) = await \HH\Asio\va($A->next(), $B->next());
				if(is_null($u) || is_null($v)) //  || $u[1]['_halted'] || $v[1]['_halted']
					break;
				// /* HH_IGNORE_ERROR[4110] Because neither are halted, one could be null because the corresponding `Tu` or `Tv` is nullable, or neither is null. */
				yield $combinator($u[1], $v[1]);
			}
		});
	}
	public static function combine_latest<Tu, Tv, Tx>(Producer<Tu> $A, Producer<Tv> $B, (function(Tu, Tv): Tx) $combinator): Producer<Tx> {
		$latest = tuple(null, null);
		return new self(new EmitIterator(Vector{
			async(EmitTrigger<Tx> $trigger) ==> {
				foreach(clone $A await as $v) {
					$latest[0] = $v;
					list($u, $v) = $latest;
					if(!is_null($u) && !is_null($v))
						$trigger($combinator($u, $v));
				}
			}, async(EmitTrigger<Tx> $trigger) ==> {
				foreach(clone $B await as $v) {
					$latest[1] = $v;
					list($u, $v) = $latest;
					if(!is_null($u) && !is_null($v))
						$trigger($combinator($u, $v));
				}
			}
		}));
	}
	public static function join<Tu, Tv, Tx>(Producer<Tu> $A, Producer<Tv> $B, (function(Tu): Awaitable<mixed>) $A_timer, (function(Tv): Awaitable<mixed>) $B_timer, (function(Tu, Tv): Tx) $combinator): Producer<Tx> {
		// ..gggghhhhhaaah...
		$latest_timer = tuple(async {}, async {});
		$latest = tuple(null, null);
		return new static(new Subject(Vector{
			async (Subject<Tx> $subject) ==> {
				foreach(clone $A await as $u) {
					$latest_timer[0] = $A_timer($u);
					$latest[0] = $u;
					
					$v = $latest[1];
					$subject->sidechain($latest_timer[0]);
					if(!is_null($v) && !\HH\Asio\has_finished($latest_timer[1]))
						$subject->emit($combinator($u, $v));
				}
			},
			async (Subject<Tx> $subject) ==> {
				foreach(clone $B await as $v) {
					$latest_timer[1] = $B_timer($v);
					$latest[1] = $v;
					
					$u = $latest[0];
					$subject->sidechain($latest_timer[1]);
					if(!is_null($u) && !\HH\Asio\has_finished($latest_timer[0]))
						$subject->emit($combinator($u, $v));
				}
			}
		}));
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