<?hh // strict
namespace HHReactor;
use HHReactor\Asio\DelayedEmptyAsyncIterator;
use HHReactor\Collection\EmptyAsyncIterator;
use HHReactor\Collection\Queue;
use function HHReactor\Asio\voidify;
newtype Racecar<+T> = shape('engine' => AsyncIterator<T>, 'driver' => ?Awaitable<mixed>);
/**
 * An mergeable and shareable wrapper of multiple `AsyncIterator`.
 */
class Producer<+T> extends BaseProducer<T> {
	private Wrapper<?ConditionWaitHandle<mixed>> $bell;
	private Vector<Racecar<T>> $racetrack = Vector{}; // this default prevents a race condition between initialization 
	protected function __construct(Vector<(function(Appender<T>): AsyncIterator<T>)> $generator_factories) {
		// If I want this to be protected but other BaseProducer-children to maybe have public constructors, then I've gotta do this in each one. Kind of a pain, but (shrug emoji)
		$this->buffer = new Queue();
		$this->running_count = new Wrapper(0);
		// $this->refcount = new Wrapper(1);
		$this->some_running = new Wrapper(new Wrapper(false));
		
		$this->bell = new Wrapper(null);
		
		$this->racetrack = $this->racetrack->concat($generator_factories->map(($factory) ==> shape('engine' => $factory(($iterator) ==> $this->_append($iterator)), 'driver' => null)));
	}
	
	public function sidechain(Awaitable<mixed> $incoming): void {
		$this->_append(new DelayedEmptyAsyncIterator($incoming));
	}
	
	private function awaitify(AsyncIterator<T> $incoming): Awaitable<void> {
		$stashed_some_running = $this->some_running->get();
		return async {
			do {
				$v = await \HH\Asio\wrap($incoming->next());
				$bell = $this->bell->get();
				if(!is_null($bell) && !\HH\Asio\has_finished($bell))
					$bell->succeed(null);
				
				if($v->isFailed() || !is_null($v->getResult()))
					/* HH_FIXME[4110] is_null on result not sufficient to refine ResultOrExceptionWrapper<?T> to ResultOrExceptionWrapper<T> */
					$this->buffer->add($v);
				
				if($v->isFailed() || is_null($v->getResult()))
					return; // tempted to replace this with the uncaught exception to end the iterator, but that seems a bit... blunt?
					
				if(false === $stashed_some_running->get()) {
					return;
				}
			}
			while(!$v->isFailed() && !is_null($v));
		};
	}
	
	public function get_iterator_edge(): WaitHandle<void> {
		return voidify(\HH\Asio\v($this->racetrack->mapWithKey(($k, $racecar) ==> {
			$driver = $racecar['driver'];
			if(is_null($driver))
				return ($this->racetrack[$k]['driver'] = $this->awaitify($racecar['engine']));
			else
				return $driver;
		})));
	}
	
	public function is_paused(): bool {
		return !$this->some_running->get()->get();
	}
	
	private function _append(AsyncIterator<T> $incoming): void {
		$bell = $this->bell->get();
		if(!is_null($bell) && !\HH\Asio\has_finished($bell))
			$bell->succeed(null);
		
		// hopefully the ordering of these instructions doesn't matter
		// this check wouldn't be necessary if these were pure generators, because the generators couldn't call the extender before the first `next`.
		
		$driver = null;
		if(true === $this->some_running->get()->get())
			$driver = $this->awaitify($incoming);
		$this->racetrack->add(shape('engine' => $incoming, 'driver' => $driver));
	}
	
	protected function _attach(): void {
		// I do want to reset the value, right?
		foreach($this->racetrack as $k => $racecar) {
			$this->racetrack[$k]['driver'] = async {
				$driver = $racecar['driver'];
				if(!is_null($driver) && !\HH\Asio\has_finished($driver->getWaitHandle()))
					await $driver;
				$awaited = $this->awaitify($racecar['engine']);
				await $awaited;
			};
		}
	}
	
	protected function _detach(): void {
		parent::_detach();
		
		// detach from children iterators to conserve memory during pausing
		if(false === $this->some_running->get()->get()) {
			foreach($this->racetrack as $racecar) {
				$engine = $racecar['engine'];
				if($engine instanceof BaseProducer)
					$engine->_detach();
			}
		}
	}
	
	protected async function _produce(): Awaitable<?(mixed, T)> {
		while($this->buffer->is_empty()) {
			try {
				$bell = $this->bell->get();
				if(is_null($bell) || \HH\Asio\has_finished($bell)) {
					$this->bell->set(ConditionWaitHandle::create($this->get_iterator_edge()));
				}
				await $this->bell->get();
			}
			catch(\InvalidArgumentException $e) {
				if($e->getMessage() !== 'ConditionWaitHandle not notified by its child')
					throw $e;
				else {
					// if($this->some_running->get()) {
					// 	// Assume that if the some_running flag is still set, the iterator just finished and this is the first `next` to know about it.
					// 	// Assume that some_running would be unset by the __destruct-detach impl if this Producer was paused from orphaning
					// 	$this->some_running->set(false);
					// }
					return null; // either the generators are exhausted, or they aborted from orphaning. Either way, this iterator is finished as far as the calling scope is concerned: end the iterator
				}
			}
		}
		return $this->buffer->shift()->getResult();
	}
	
	/**
	 * Wrap an `AsyncIterator` in a `Producer` (static type), reproducing values from it
	 */
	final public static function create(AsyncIterator<T> $incoming): Producer<T> {
		return new self(Vector{ ($_) ==> $incoming });
	}
	
	/**
	 * Wrap an `AsyncIterator` in a `Producer` (exactly Producer type), reproducing values from it
	 */
	final public static function create_producer<Tv>(AsyncIterator<Tv> $incoming): Producer<Tv> {
		return new self(Vector{ ($_) ==> $incoming });
	}
	
	/**
	 * `just` equivalent for `Awaitable` {@see \HHReactor\Producer::just()}
	 */
	final public static function create_from_awaitable(Awaitable<T> $awaitable): Producer<T> {
		return self::create_from_awaitables(Vector{ $awaitable });
	}
	
	/**
	 * Race a collection of `Awaitable`s in parallel and stream the outputs as they resolve
	 */
	final public static function create_from_awaitables(Vector<Awaitable<T>> $awaitables): Producer<T> {
		return new self(
			$awaitables->map(($awaitable) ==> (async ($_) ==> {
				$v = await $awaitable;
				yield $v;
			}))
		);
	}
	
	/**
	 * Awaitable of _at least_ the lifetime of the producer containing the values emitted since call.
	 * 
	 * **Spec**: 
	 * - The return value must not resolve sooner than the Producer throws an Exception, or runs out of values. The return value may resolve at _any point_ afterwards, but must resolve eventually.
	 * - Any values produced after even the _beginning_ of the call must be included in the return value.
	 */
	public async function collapse(): Awaitable<\ConstVector<T>> {
		$accumulator = Vector{};
		foreach(clone $this await as $v)
			$accumulator->add($v);
		return $accumulator;
	}
	
	/**
	 * Iterator to dispense values produced since the last call to {@see HHReactor\Collection\Producer::next()} or {@see HHReactor\Collection\Producer::fast_forward()}.
	 * 
	 * Note: as with all things to do with the buffer, fast-forwarding one Producer does not fast-forward cloned Producers.
	 */
	public function fast_forward(): Iterator<T> {
		// no risk of "Generator already started" or "Changed during iteration" exceptions, because there are no underlying core Hack collections in LinkedList iterables
		while(!$this->buffer->is_empty()) {
			$next = $this->buffer->shift();
			if(!is_null($next))
				yield $next->getResult()[1];
		}
	}
	
	// ReactiveX-ish operators
	/**
	 * [Transform the items [produced] by [a Producer] by applying a function to each item](http://reactivex.io/documentation/operators/map.html)
	 * 
	 * **Spec**:
	 * - The transformed values of all items produced after even the _beginning_ of the call must be included in the return value.
	 * - Note: order is not guaranteed to be preserved (although it is highly likely)
	 * @param $f - Transform items to type of choice `Tv`.
	 * @return - Emit transformed items from this Producer.
	 */
	public function map<Tv>((function(T): Tv) $f): Producer<Tv> {
		return static::create_producer(async {
			foreach(clone $this await as $v) {
				yield $f($v);
			}
		});
	}
	
	/**
	 * [Emit only those items from an Producer that pass a predicate test](http://reactivex.io/documentation/operators/filter.html)
	 * 
	 * **Spec**:
	 * - Order from the initial `Producer` is preserved in the return value.
	 */
	public function filter<Tv>((function(T): bool) $f): Producer<T> {
		return static::create_producer(async {
			foreach(clone $this await as $v)
				if($f($v))
					yield $v;
		});
	}
	
	
	/**
	 * [Apply a function to each item emitted by [a Producer], sequentially, and emit each successive value](http://reactivex.io/documentation/operators/scan.html)
	 * 
	 * **Spec**:
	 * - If there is exactly one value, then no values will be produced by the returned Producer. Otherwise, all values produced after even the _beginning_ of the call must be combined and included in the return value in order of production in the source Producer.
	 * @param $f - Transform two consecutive items to type of choice `Tv`.
	 * @return - Emit scanned items from this producer.
	 */
	public function scan<Tv super T>((function(T, T): Tv) $f): Producer<Tv> {
		$last = null;
		return static::create_producer(async {
			foreach(clone $this await as $v) {
				if(!is_null($last))
					yield $f($last, $v);
				else
					yield $v;
				
				$last = $v;
			}
		});
	}
	
	/**
	 * [emit only the first _n_ items emitted by an Producer](http://reactivex.io/documentation/operators/take.html)
	 * 
	 * **Specs**
	 * - The return value may produce at most `$n` values, but must include all items produced since the beginning of the call or `$n` values, whichever is smaller.
	 */
	public function take(int $n): Producer<T> {
		return static::create(async {
			$i = 0;
			foreach(clone $this await as $v) {
				if($i++ < $n)
					yield $v;
			}
		});
	}
	
	/**
	 * [Emit only the last item emitted by an Producer](http://reactivex.io/documentation/operators/last.html)
	 * 
	 * **Spec**: 
	 * - The return value must not resolve sooner than the Producer throws an Exception or runs out of values. The return value may resolve at _any point_ afterwards, but must resolve eventually.
	 * - If and only if no values are produced after the beginning of the call, the return value will resolve to null.
	 * @return - Contain the last value
	 */
	public async function last(): Awaitable<?T> {
		$v = null;
		foreach(clone $this await as $v) {}
		return $v;
	}
	
	/**
	 * [Emit only the first item emitted by a Producer](http://reactivex.io/documentation/operators/first.html)
	 * 
	 * **Spec**: 
	 * - If and only if no values are produced after the beginning of the call, the return value will resolve to null.
	 * @return - Contain the first value
	 */
	public async function first(): Awaitable<?T> {
		foreach(clone $this await as $v) {
			return $v;
		}
		return null;
	}
	
	/**
	 * [Apply a function to each item emitted by an Producer, sequentially, and emit the final value](http://reactivex.io/documentation/operators/reduce.html)
	 * 
	 * **Spec**:
	 *   - If there is exactly one value, then the returned `Awaitable` will resolve to `null`. Otherwise, all values produced after even the _beginning_ of the call must be combined and included in the return value in order of production in the source Producer.
	 * @param $f - Transform two consecutive items to type of choice `Tv`.
	 * @return - Emit the final result of sequential reductions.
	 */
	public function reduce<Tv super T>((function(T, T): Tv) $f): Awaitable<?Tv> {
		return $this->scan($f)->last();
	}
	
	/**
	 * [Transform the items emitted by an Producer into Producers, then flatten the emissions from those into a single Producer](http://reactivex.io/documentation/operators/flatmap.html)
	 * 
	 * **Spec**:
	 *   - `Tv`-typed items from the return value might not preserve the order they are produced in _separate_ Producers created by `$meta`.
	 * - **Preferred**:
	 *   - All ordering must be preserved.
	 * @param $meta - Transform `T`-valued items to Producers. E.g. for `T := Producer<Tv>`, $meta may just be the identity function.
	 */
	public function flat_map<Tv>((function(T): Producer<Tv>) $meta): Producer<Tv> {
		$clone = clone $this;
		return new self(Vector{ ($appender) ==> 
			new DelayedEmptyAsyncIterator(async {
				foreach($clone await as $seed) {
					$subclone = clone $meta($seed);
					$appender($subclone);
				}
			}) }
		);
	}
	/**
	 * [Convert an Producer that emits Producers into a single Producer that emits the items emitted by the most-recently-emitted of those Producers](http://reactivex.io/documentation/operators/switch.html)
	 * 
	 * Note: by virtue of the limited Hack async spec, nothing is guaranteed about the ordering or timing of `switch`, although you can rely on it being close to the expected "perfect" behavior outlined by the ReactiveX documeentation.
	 * @param $meta - Transform `T`-valued items to Producers. E.g. for `T := Producer<Tv>`, $meta may just be the identity function.
	 */
	public function switch_on_next<Tv>((function(T): Producer<Tv>) $meta): Producer<Tv> {
		$clone = clone $this;
		return new self(
			Vector{ ($appender) ==> 
				new DelayedEmptyAsyncIterator(async {
					$current_idx = new Wrapper(-1);
					foreach($clone await as $seed) {
						$i = $current_idx->get();
						$current_idx->set(++$i);
						
						$subclone = clone $meta($seed);
						$appender(async {
							foreach($subclone await as $v)
								if($current_idx->get() === $i) // eh, not the most performant way... but meh, until it becomes a problem
									yield $v;
						});
					}
				})
			}
		);
	}
	/**
	 * [Divide an Producer into a set of Producers that each emit a different subset of items from the original Producer.](http://reactivex.io/documentation/operators/groupby.html)
	 * 
	 * **Spec**:
	 * - Any items produced after the beginning call in the original Producer must be produced by exactly one of the `this`-typed Producers in the return value.
	 * @param $keysmith - Assign `Tk`-valued keys to `T`-valued items
	 */
	public function generalized_group_by<Tk as arraykey>((function(T): Traversable<Tk>) $keysmith): Producer<Producer<T>> {
		// materializing subjects out of vectors and conditions... not my proudest moment
		$clone = clone $this;
		$subjects = Map{}; // Map<Tk, (ConditionWaitHandle<mixed>, SplQueue<T>)>
		$producer_wrapper = new Wrapper(null);
		$producer_wrapper->set(self::create_producer(async {
			await \HH\Asio\later();
			$self_producer = $producer_wrapper->get();
			invariant(!is_null($self_producer), 'By construction, this must by this point be set to this the producer wrapping this iterator.');
			
			// note: fail the trunk producer if this $clone value producer fails.
			foreach($clone await as $v) {
				foreach($keysmith($v) as $key) {
					if(!$subjects->containsKey($key)) {
						// add emittee
						
						$subjects->set($key, tuple(
							ConditionWaitHandle::create(\HHReactor\Asio\lifetime(Vector{ clone $self_producer }, Vector{ \HH\Asio\later() })->getWaitHandle()),
							new \SplQueue()
						)); // later() for just in case iterator has finished: we don't want the ConditionWaitHandle to throw.
						$subjects[$key][1]->enqueue($v);
						$subjects[$key][0]->succeed($v);
						yield static::create(async {
							while(true) {
								while(!$subjects[$key][1]->isEmpty())
									yield $subjects[$key][1]->dequeue();
								
								try {
									await $subjects[$key][0];
									$subjects[$key][0] = ConditionWaitHandle::create(\HHReactor\Asio\lifetime(Vector{ clone $self_producer })->getWaitHandle());
								}
								catch(\InvalidArgumentException $e) {
									if($e->getMessage() !== 'ConditionWaitHandle not notified by its child')
										throw $e;
									return;
								}
							}
						});
					}
					else {
						$subjects[$key][1]->enqueue($v);
						if(!\HH\Asio\has_finished($subjects[$key][0]))
							$subjects[$key][0]->succeed($v);
					}
				}
			}
		}));
		$producer = $producer_wrapper->get();
		invariant(!is_null($producer), 'Producer is unconditionally set just above.');
		return $producer;
	}
	
	public function group_by<Tk as arraykey>((function(T): Tk) $keysmith): Producer<Producer<T>> {
		return $this->generalized_group_by(($v) ==> [ $keysmith($v) ]);
	}
	
	/**
	 * [Periodically gather items emitted by an Producer into bundles and emit these bundles rather than emitting the items one at a time.](http://reactivex.io/documentation/operators/buffer.html)
	 * 
	 * **Spec**:
	 * - Any values produced by the original Producer after a call to `buffer` must be included in the return value.
	 * @param $signal - Produce a value whenever a new buffer is to replace the current one.
	 * @return - Produce Collections (which may be empty) bundling values emitted during each buffering period, as dictated by `$signal`.
	 */
	public function buffer(Producer<mixed> $signal): Producer<\ConstVector<T>> {
		return static::create_producer(async {
			$clone = clone $this;
			foreach(clone $signal await as $_)
				yield new Vector($clone->fast_forward());
		});
	}
	
	/**
	 * [Only emit an item from an Producer if a particular timespan has passed without it emitting another item](http://reactivex.io/documentation/operators/debounce.html)
	 * 
	 * **Spec**
	 * - The last value of the original Producer, if there is one, must be produced in the return value.
	 * @param $usecs - The "timespan" as described above, in microseconds.
	 */
	
	// public function debounce(int $usecs): Producer<T> {
	// 	$clone = clone $this;
	// 	return new self(Vector{ ($appender) ==> {
	// 		foreach()
	// 	} })
	// }
	
	// public function debounce(int $usecs): Producer<T> {
	// 	$clone = clone $this;
	// 	$extendable = new Wrapper(new Extendable($clone->next()));
	// 	return new self(($appender) ==> Vector{
	// 		async {
	// 			$first_vec = await $extendable->get();
	// 			$k_v = $first_vec->lastValue();
	// 			$appender(new DelayedEmptyAsyncIterator(async {
	// 				while(!is_null($k_v)) {
	// 					$delayed_k_v = self::_delay_return($usecs, $k_v);
	// 					if($extendable->get()->getWaitHandle()->isFinished())
	// 						$extendable->set(new Extendable($delayed_k_v));
	// 					else
	// 						$extendable->get()->extend($delayed_k_v);
	// 					$k_v = await $clone->next();
	// 				}
	// 			}));
	// 			// ($k_v == null) \implies (\HH\Asio\has_finished($clone))
	// 			while(!\HH\Asio\has_finished($clone->get_lifetime())) {
	// 				$V = await $extendable->get();
	// 				$last = $V->lastValue();
	// 				if(!is_null($last))
	// 					yield $last[1];
	// 			}
	// 		}
	// 	});
	// }
	
	/**
	 * [periodically subdivide items from an Producer into Producer windows and emit these windows rather than emitting the items one at a time](http://reactivex.io/documentation/operators/window.html)
	 * 
	 * Note: if the `$signal` ends prematurely (before the end of the source `Producer`), the items continue to be produced on the last window.
	 * @param $signal - Produce a value whenever a new window opens.
	 * @return - Produce Producers that group values from the original into windows dictated by `$signal`.
	 */
	public function window(Producer<mixed> $signal): Producer<Producer<T>> {
		$clone = clone $this;
		$signal_clone = clone $signal;
		$finished = new Wrapper(false);
		return static::create_producer(async {
			$i = new Wrapper(0);
			do {
				yield static::create(async {
					$stashed_i = $i->get();
					// NOTE LACK OF CLONE: producers yielded from here will race with each other for values. Order not guaranteed at boundary between windows.
					foreach($clone await as $v) {
						yield $v;
						if($i->get() !== $stashed_i)
							// the window has closed
							// note: we'll keep producing on this window if the windower stops producing
							return;
					}
					$finished->set(true);
				});
				$next = await $signal_clone->next();
				$i->v++;
			}
			while(!is_null($next) && !$finished->get()); // a more naive stop condition, but much more expressive
			// while($signal_clone->is_producing()); // this is more technically correct, but for now there isn't any difference here, and the former is easier to debug
		});
	}
	
	/**
	 * [Emit the most recent items emitted by an Producer within periodic time intervals](http://reactivex.io/documentation/operators/sample.html)
	 * 
	 * @param $signal - Produce a value whenever a new window opens.
	 * @return - Produce the last value emitted during a window dictated by `$signal`.
	 */
	public function sample(Producer<mixed> $signal): Producer<?T> {
		return static::create_producer(async {
			foreach($this->window($signal) await as $producer) {
				$v = await $producer->last();
				yield $v;
			}
		});
	}
	
	/**
	 * Convenience method for producing a (probably) ordered sequence of stepped integers.
	 * 
	 * Note: in a free `foreach-await` loop, this will _busy-wait_ until a parallel coroutine wakes up if ever
	 */
	public static function count_up(int $start = 0, int $step = 1): Producer<int> {
		return static::create_producer(async {
			for(;;$start += $step) {
				yield $start;
				await \HH\Asio\later();
			}
		});
	}
	
	/**
	 * [Create an Producer that emits no items but terminates normally](http://reactivex.io/documentation/operators/empty-never-throw.html)
	 * 
	 * Note: very likely to, but _might_ not terminate immediately.
	 */
	public static function empty(): Producer<T> {
		return static::create(new EmptyAsyncIterator());
	}
	
	/**
	 * [Create an Producer that emits no items and terminates with an error](http://reactivex.io/documentation/operators/empty-never-throw.html)
	 */
	public static function throw(\Exception $e): Producer<T> {
		return new self(async {
			throw $e;
		});
	}
	/**
	 * [Create an Producer that emits a sequence of integers spaced by a given time interval](http://reactivex.io/documentation/operators/interval.html)
	 * 
	 * Note: in extremely high-concurrency situations, this might get very inaccurate _and_ skewed.
	 */
	public static function interval(int $usecs): Producer<int> {
		return static::create_producer(async {
			for($i = 0; ; $i++) {
				await \HH\Asio\usleep($usecs);
				yield $i;
			}
		});
	}
	/**
	 * [create an Producer that emits a particular item](http://reactivex.io/documentation/operators/just.html)
	 * 
	 * Note: very likely to, but _might_ not terminate immediately.
	 */
	public static final function just(T $v): Producer<T> {
		return static::create(async {
			yield $v;
		});
	}
	/**
	 * [Create an Producer that emits a particular range of sequential integers](http://reactivex.io/documentation/operators/range.html)
	 * 
	 * Note: in a free `foreach-await` loop, this will _busy-wait_ until it ends or a parallel coroutine wakes up if ever
	 */
	public static function range(int $n, int $m): Producer<int> {
		return static::create_producer(async {
			for(; $n < $m; $n++) {
				yield $n;
				await \HH\Asio\later();
			}
		});
	}
	/**
	 * [Create an Producer that emits a particular item multiple times](http://reactivex.io/documentation/operators/repeat.html)
	 * 
	 * Note: in a free `foreach-await` loop, this will _busy-wait_ until it ends or a parallel coroutine wakes up if ever
	 * @param $v The value to repeat
	 * @param $n Nullable number of repeats; null will continue forever
	 */
	public static final function repeat(T $v, ?int $n = null): Producer<T> {
		return static::create(async {
			for(; is_null($n) || $n > 0; !is_null($n) && $n--) {
				yield $v;
				await \HH\Asio\later();
			}
		});
	}
	/**
	 * [Create an Producer that emits a particular sqequence of items multiple times](http://reactivex.io/documentation/operators/repeat.html)
	 * 
	 * Note: in a free `foreach-await` loop, this will _busy-wait_ until it ends or a parallel coroutine wakes up if ever
	 * @param $v The value to repeat
	 * @param $n Nullable number of repeats; null will continue forever
	 */
	public static final function repeat_sequence(Traversable<T> $vs, ?int $n = null): Producer<T> {
		return static::create(async {
			for(; is_null($n) || $n > 0; !is_null($n) && $n--) {
				foreach($vs as $v) {
					yield $v;
					await \HH\Asio\later();
				}
			}
		});
	}
	/**
	 * [Create an Producer that emits a particular item after a given delay](http://reactivex.io/documentation/operators/timer.html)
	 */
	public final static function timer(T $v, int $delay): Producer<T> {
		return static::create(async {
			await \HH\Asio\usleep($delay);
			yield $v;
		});
	}
	/**
	 * [Convert a collection of `Awaitable` items to a `Producer`](http://reactivex.io/documentation/operators/from.html)
	 */
	public final static function from(Iterable<Awaitable<T>> $subawaitables): Producer<T> {
		return static::create(async {
			foreach($subawaitables as $v) {
				$v = await $v;
				yield $v;
			}
		});
	}
	/**
	 * [Combine multiple Producers into one by merging their emissions](http://reactivex.io/documentation/operators/merge.html)
	 * 
	 * Note: `merge` has no ordering guarantees, especially between the iterators, and potentially even for items within a given iterator.
	 */
	public final static function merge(Vector<AsyncIterator<T>> $producers): Producer<T> {
		return new self($producers->map(($producer) ==> ($_) ==> $producer));
	}
	/**
	 * [Combine the emissions of multiple Producers together via a specified function and emit single items for each combination based on the results of this function.](http://reactivex.io/documentation/operators/zip.html)
	 * @param $A - Producer of left-hand items
	 * @param $B - Producer of right-hand items
	 * @param $combiner - Zips left- and right-hand items to `Tx`-valued items.
	 */
	public static function zip<Tu, Tv, Tx>(Producer<Tu> $A, Producer<Tv> $B, (function(Tu, Tv): Tx) $combiner): Producer<Tx> {
		$clone_A = clone $A;
		$clone_B = clone $B;
		return static::create_producer(async {
			while(true) {
				// requires HHVM ^3.16
				list($u, $v) = await \HH\Asio\va($clone_A->next(), $clone_B->next());
				if(is_null($u) || is_null($v)) //  || $u[1]['_halted'] || $v[1]['_halted']
					break;
				// /* HH_IGNORE_ERROR[4110] Because neither are halted, one could be null because the corresponding `Tu` or `Tv` is nullable, or neither is null. */
				yield $combiner($u[1], $v[1]);
			}
		});
	}
	/**
	 * [When an item is emitted by either of two Producers, combine the latest item emitted by each Producer via a specified function and emit items based on the results of this function](http://reactivex.io/documentation/operators/combinelatest.html)
	 * 
	 * Note: if one source `Producer` never produces values, no values are produced by the return value either.
	 */
	public static function combine_latest<Tu, Tv, Tx>(Producer<Tu> $A, Producer<Tv> $B, (function(Tu, Tv): Tx) $combiner): Producer<Tx> {
		$latest = tuple(null, null);
		return new self(Vector{
			async ($appender) ==>  {
				foreach(clone $A await as $v) {
					$latest[0] = $v;
					list($u, $v) = $latest;
					if(!is_null($u) && !is_null($v))
						yield $combiner($u, $v);
				}
			}, async ($appender) ==> {
				foreach(clone $B await as $v) {
					$latest[1] = $v;
					list($u, $v) = $latest;
					if(!is_null($u) && !is_null($v))
						yield $combiner($u, $v);
				}
			}
		});
	}
	/**
	 * [Combine items emitted by two Observables whenever an item from one Observable is emitted during a time window defined according to an item emitted by the other Observable](http://reactivex.io/documentation/operators/join.html)
	 */
	public static function join<Tu, Tv, Tx>(Producer<Tu> $A, Producer<Tv> $B, (function(Tu): Awaitable<mixed>) $A_timer, (function(Tv): Awaitable<mixed>) $B_timer, (function(Tu, Tv): Tx) $combiner): Producer<Tx> {
		// ..gggghhhhhaaah...
		$latest_timer = tuple(async {}, async {});
		$latest = tuple(null, null);
		return new self(
			Vector{
				async ($appender) ==> {
					foreach(clone $A await as $u) {
						$latest_timer[0] = $A_timer($u);
						$latest[0] = $u;
						
						$v = $latest[1];
						$appender(new DelayedEmptyAsyncIterator($latest_timer[0]));
						if(!is_null($v) && !\HH\Asio\has_finished($latest_timer[1]))
							yield $combiner($u, $v);
					}
				},
				async ($appender) ==> {
					foreach(clone $B await as $v) {
						$latest_timer[1] = $B_timer($v);
						$latest[1] = $v;
						
						$u = $latest[0];
						$appender(new DelayedEmptyAsyncIterator($latest_timer[1]));
						if(!is_null($u) && !\HH\Asio\has_finished($latest_timer[0]))
							yield $combiner($u, $v);
					}
				}
			}
		);
	}
	
	private static async function _delay_return<Tv>(int $usecs, Tv $v): Awaitable<Tv> {
		await \HH\Asio\usleep($usecs);
		return $v;
	}
}