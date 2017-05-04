<?hh // strict
namespace HHReactor;
use HHReactor\Asio\DelayedEmptyAsyncIterator;
use HHReactor\Collection\Queue;
use function HHReactor\Asio\voidify;
newtype Racecar<+T> = shape('engine' => AsyncIterator<T>, 'driver' => ?Awaitable<mixed>);

<<__ConsistentConstruct>>
/**
 * An mergeable and shareable wrapper of `AsyncIterator`.
 * 
 * Feel free to skip the terminology on the first skim. Refer as needed.
 * 
 * **Terminology**:
 * - Keywords identified in [RFC2119](https://www.ietf.org/rfc/rfc2119.txt) are used with identical meaning in docblocks.
 * - _Arc_: The commands executed and time elapsed between two `await` statements without any `await` statements in between. Any arc is the _of_ the `Awaitable` at the _start_.
 * - _Resolved `Awaitable`_: Testing the `Awaitable` with `\HH\Asio\has_finished()` returns `true`.
 * - _Ready queue_: All resolved `Awaitable`s whose arcs have not yet begun. Their arcs have a real chance of beginning when control next returns to the top-level `HH\Asio\join`.
 * - _Endpoint_: Exactly one of the following that applies:
 *   1. The beginning of a {@see HHReactor\Collection\Producer::halt()} call to this Producer.
 *   2. The propagation of an `Exception` from any {@see HHReactor\Collection\Producer::next()} call
 *   3. The propagation of a `null` from any {@see HHReactor\Collection\Producer::next()} call
 *   - _Endpoint equality_: If two endpoints are resolved at the same time, but have not yet ended any of their respective consumers, they are equal. Put a different way, endpoints are equal up to the same ready queue.
 * - _Lifetime_: The union of all arcs from the creation of the Producer to the endpoint.
 */
class Producer<+T> extends BaseProducer<T> {
	private Wrapper<?ConditionWaitHandle<mixed>> $bell;
	private Vector<Racecar<T>> $racetrack;
	final protected function __construct(Vector<(function(Appender<T>): AsyncIterator<T>)> $generator_factories) {
		// If I want this to be protected but other BaseProducer-children to maybe have public constructors, then I've gotta do this in each one. Kind of a pain, but (shrug emoji)
		$this->buffer = new Queue();
		$this->running_count = new Wrapper(0);
		// $this->refcount = new Wrapper(1);
		$this->some_running = new Wrapper(new Wrapper(false));
		
		$this->bell = new Wrapper(null);
		
		$this->racetrack = $generator_factories->map(($factory) ==> shape('engine' => $factory(($iterator) ==> $this->_append($iterator)), 'driver' => null));
	} // Note: cold-ish
	
	public function sidechain(Awaitable<mixed> $incoming): void {
		$this->_append(new DelayedEmptyAsyncIterator($incoming));
	}
	
	private function awaitify(AsyncIterator<T> $incoming): Awaitable<void> {
		$stashed_some_running = $this->some_running->get();
		return async {
			foreach($incoming await as $v) {
				$bell = $this->bell->get();
				if(!is_null($bell) && !\HH\Asio\has_finished($bell))
					$bell->succeed(null);
				
				$this->buffer->add($v);
				if(false === $stashed_some_running->get()) {
					return;
				}
			}
		};
	}
	
	private function driver_lifetimes(): WaitHandle<void> {
		return voidify(\HH\Asio\v($this->racetrack->map(($racecar) ==> {
			$driver = $racecar['driver'];
			invariant(!is_null($driver), 'Implementation error: expected driving Awaitable to be set before `_produce`.');
			return $driver;
		})));
	}
	
	public function is_paused(): bool {
		return !$this->some_running->get();
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
	
	protected async function _produce(): Awaitable<?(mixed, T)> {
		while($this->buffer->is_empty()) {
			try {
				$bell = $this->bell->get();
				if(is_null($bell) || \HH\Asio\has_finished($bell)) {
					$this->bell->set(ConditionWaitHandle::create($this->driver_lifetimes()));
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
		return tuple(null, $this->buffer->shift());
	}
	
	final public static function create(AsyncIterator<T> $incoming): this {
		return new static(Vector{ ($_) ==> $incoming });
	}
	
	final public static function create_producer<Tv>(AsyncIterator<Tv> $incoming): Producer<Tv> {
		return new self(Vector{ ($_) ==> $incoming });
	}
	
	/**
	 * Awaitable that must resolve at some point after the endpoint.
	 * 
	 * **Timing**:
	 * - **Spec**: The return value must not resolve faster than the Producer is `halt`ed, throws an Exception, or runs out of values. The return value may resolve at _any point_ afterwards, but must resolve eventually.
	 * 
	 * **Sharing**: Isolated. Lifetimes of cloned and derivative Producers must not affect the Timing clauses of each other.
	 */
	// public function get_lifetime(): Awaitable<mixed> {
	// 	return $this->total_awaitable;
	// }
	
	/**
	 * Iterator to dispense values produced since the last call to {@see HHReactor\Collection\Producer::next()} or {@see HHReactor\Collection\Producer::fast_forward()}.
	 * 
	 * **Timing**: No known timing issues.
	 * 
	 * **Sharing**: Isolated. Fast-forwarding one Producer does not fast-forward cloned Producers.
	 */
	public function fast_forward(): Iterator<T> {
		// no risk of "Generator already started" or "Changed during iteration" exceptions, because there are no underlying core Hack collections in LinkedList iterables
		while(!$this->buffer->is_empty()) {
			$next = $this->buffer->shift();
			if(!is_null($next))
				yield $next;
		}
	}
	
	/**
	 * Stop EmitIterator from broadcasting values.
	 * 
	 * **Timing**:
	 * - **Spec**: 
	 *   - Depends heavily on {@see HHReactor\Collection\EmitIterator::next()}
	 *   - Any items produced before `await`ing the return value, including those during the current async arc, must eventually be broadcasted. This arc ends when the return value is `await`ed.
	 * - **Preferred**: All pending calls to {@see HHReactor\Collection\Producer::next()} would immediately return `null` after control is returned to the top scope, ending all consumers of the Producer.
	 * 
	 * **Sharing**: Unisolated. Halting one Producer halts all cloned Producers.
	 * @see HHReactor\Asio\Haltable
	 * @param $e - Halt with Exception, or only with signal
	 * @return
	 */
	// public function soft_halt(?\Exception $e = null): void {
	// 	foreach($this->emitters as $emitter)
	// 		$emitter->soft_halt($e);
		
	// 	$this->total_awaitable->soft_halt($e);
		
	// 	if(!$this->bell->get()->isFinished())
	// 		$this->bell->get()->succeed(null);
	// }
	
	// public static function create_from_iterator<Tv>(AsyncIterator<Tv> $iterator): EmitIterator<Tv> {
	// 	return self::create_from_iterators(Vector{ $iterator });
	// }
	
	// public static function create_from_iterators<Tv>(
	// 	Iterable<AsyncIterator<Tv>> $iterators,
	// 	(function(Iterable<Awaitable<mixed>>): Awaitable<mixed>) $reducer = fun('\HH\Asio\v')): EmitIterator<Tv> {
	// 	return new self(($_) ==> $iterators, $reducer);
	// }
	
	final public static function create_from_awaitable(Awaitable<T> $awaitable): this {
		return self::create_from_awaitables(Vector{ $awaitable });
	}
	
	final public static function create_from_awaitables(Vector<Awaitable<T>> $awaitables): this {
		return new static(
			$awaitables->map(($awaitable) ==> (async ($_) ==> {
				$v = await $awaitable;
				yield $v;
			}))
		);
	}
	
	// protected static function create_by_unicasting_emittee<Tv>(
	// 	(function(Emittee<Tv>): Awaitable<mixed>) $importer,
	// 	(function(Iterable<Awaitable<mixed>>): Awaitable<mixed>) $reducer = fun('\HH\Asio\v')): EmitIterator<Tv> {
	// 	return self::create_by_multicasting_emittee(($emittee) ==> Vector{ $importer($emittee) });
	// }
	
	// protected static function create_by_multicasting_emittee<Tv>(
	// 	(function(Emittee<Tv>): Iterable<Awaitable<mixed>>) $importers,
	// 	(function(Iterable<Awaitable<mixed>>): Awaitable<mixed>) $reducer = fun('\HH\Asio\v')): EmitIterator<Tv> {
	// 	return new self(
	// 		(Emittee<Tv> $emittee) ==> $importers($emittee)->map(($importer) ==> new DelayedEmptyAsyncIterator($importer)),
	// 	$reducer);
	// }
	
	// protected static function _iterators_to_emitters<Tv>(Iterable<AsyncIterator<Tv>> $iterators): (function(Emittee<Tv>): Iterable<AsyncIterator<Awaitable<mixed>>>) {
	// 	return (Emittee<Tv> $emittee) ==> $iterators->map(($iterator) ==> {
	// 		return new DelayedEmptyAsyncIterator(async {
	// 			foreach($iterator await as $v)
	// 				$emittee($v);
	// 		});
	// 	});
	// }
	
	/**
	 * Awaitable of _at least_ the lifetime of the producer containing the values emitted since call.
	 * 
	 * **Timing**:
	 * - **Spec**: 
	 *   - The return value must not resolve sooner than the Producer is `halt`ed, throws an Exception, or runs out of values. The return value may resolve at _any point_ afterwards, but must resolve eventually.
	 *   - Any values produced after even the _beginning_ of the call must be included in the return value.
	 */
	public async function collapse(): Awaitable<\ConstVector<T>> {
		$accumulator = Vector{};
		foreach(clone $this await as $v)
			$accumulator->add($v);
		return $accumulator;
	}
	
	// ReactiveX-ish operators
	/**
	 * [Transform the items [produced] by [a Producer] by applying a function to each item](http://reactivex.io/documentation/operators/map.html)
	 * 
	 * **Timing**:
	 * - **Spec**:
	 *   - The transformed values of all items produced after even the _beginning_ of the call must be included in the return value in order of production in the source Producer.
	 *   - The resulting Producer's endpoint must be equal to the source Producer's endpoint.
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
	 * **Timing**:
	 * - **Spec**:
	 *   - If there is exactly one value, then no values will be produced by the returned Producer. Otherwise, all values produced after even the _beginning_ of the call must be combined and included in the return value in order of production in the source Producer.
	 *   - The resulting Producer's endpoint must be equal to the source Producer's endpoint.
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
	 * [Emit only the last item (*snip*) emitted by an Observable](http://reactivex.io/documentation/operators/last.html)
	 * 
	 * **Timing**:
	 * - **Spec**: 
	 *   - The return value must not resolve sooner than the Producer is `halt`ed, throws an Exception, or runs out of values. The return value may resolve at _any point_ afterwards, but must resolve eventually.
	 *   - Any values produced after even the _beginning_ of the call must be included in the return value.
	 * @return - Contain the last value
	 */
	public async function last(): Awaitable<?T> {
		$v = null;
		foreach(clone $this await as $v) {}
		return $v;
	}
	
	/**
	 * [Emit only the first item (*snip*) emitted by an Observable](http://reactivex.io/documentation/operators/first.html)
	 * 
	 * **Timing**:
	 * - **Spec**: 
	 *   - The return value must not resolve sooner than the Producer is `halt`ed, throws an Exception, or runs out of values. The return value may resolve at _any point_ afterwards, but must resolve eventually.
	 *   - Any values produced after even the _beginning_ of the call must be included in the return value.
	 * @return - Contain the first value
	 */
	public async function first(): Awaitable<?T> {
		foreach(clone $this await as $v) {
			return $v;
		}
		return null;
	}
	
	/**
	 * [Apply a function to each item emitted by an Observable, sequentially, and emit the final value](http://reactivex.io/documentation/operators/reduce.html)
	 * 
	 * **Timing**:
	 * - **Spec**:
	 *   - If there is exactly one value, then the returned `Awaitable` will resolve to `null`. Otherwise, all values produced after even the _beginning_ of the call must be combined and included in the return value in order of production in the source Producer.
	 *   - The resulting Producer's endpoint must be equal to the source Producer's endpoint.
	 * @param $f - Transform two consecutive items to type of choice `Tv`.
	 * @return - Emit the final result of sequential reductions.
	 */
	public function reduce<Tv super T>((function(T, T): Tv) $f): Awaitable<?Tv> {
		return $this->scan($f)->last();
	}
	
	/**
	 * [Transform the items emitted by an Observable into Observables, then flatten the emissions from those into a single Observable](http://reactivex.io/documentation/operators/flatmap.html)
	 * 
	 * **Timing**:
	 * - **Spec**:
	 *   - `Tv`-typed items from the return value may not preserve the order they are produced in _separate_ Producers created by `$meta`.
	 *   - However, `Tv`-typed items  from the return value originating from _the same_ `$meta`-created Producer must preserve the order they are originally produced (must assume yielding preserves call order).
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
	 * [Convert an Observable that emits Observables into a single Observable that emits the items emitted by the most-recently-emitted of those Observables](http://reactivex.io/documentation/operators/switch.html)
	 * 
	 * **Timing**:
	 * - Depends heavily on {@see HHReactor\Asio\EmitIterator}
	 * - **Spec**:
	 *   - In general, it is possible no `Tv`-typed items are prevented from being emitted, if all `Tv` items from the current `Producer<Tv>` are produced in the same ready queue as the next `Producer<Tv>` is produced.
	 *   - However, items produced by an overtaking `Producer<Tv>` must not emit before items of the `Producer<Tv>` it is overtaking (must assume yielding preserves call order).
	 *   - The beginning events of the `Producer<Tv>`s must preserve the order of the original items.
	 * - **Preferred**
	 *   - Values produced in the current Producer after the underlying event for the next item in the original Producer resolves must not be included in the return value.
	 * @param $meta - Transform `T`-valued items to Producers. E.g. for `T := Producer<Tv>`, $meta may just be the identity function.
	 */
	public function switch_on_next<Tv>((function(T): Producer<Tv>) $meta): Producer<Tv> {
		$clone = clone $this;
		return new static(
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
	 * [Divide an Observable into a set of Observables that each emit a different subset of items from the original Observable.](http://reactivex.io/documentation/operators/groupby.html)
	 * **Timing**:
	 * - Depends heavily on {@see HHReactor\Collection\Producer::_listen_produce()}
	 * - Depends heavily on {@see HHReactor\Asio\ResettableConditionWaitHandle}
	 * - **Spec**:
	 *   - The endpoint of any `this`-typed Producer in the return value must be at least as late as the endpoint of the original Producer.
	 *   - Any items produced after this call in the original Producer must be produced by exactly one of the `this`-typed Producers in the return value. (`clone $this` before `await`; all paths lead to {@see ResettableConditionWaitHandle::succeed()})
	 *   - The order of the `T`-typed items produced by `this`-typed Producers in the return value must preserve the order of the original Producer, even between Producers corresponding to different keys (must assume {@see HHreactor\Asio\ResettableConditionWaitHandle::succeed()} preserves call order).
	 *   - There must not be a cyclic dependency.
	 * @param $keysmith - Assign `Tk`-valued keys to `T`-valued items
	 */
	public function generalized_group_by<Tk as arraykey>((function(T): Traversable<Tk>) $keysmith): Producer<this> {
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
	
	public function group_by<Tk as arraykey>((function(T): Tk) $keysmith): Producer<this> {
		return $this->generalized_group_by(($v) ==> [ $keysmith($v) ]);
	}
	
	/**
	 * [Periodically gather items emitted by an Observable into bundles and emit these bundles rather than emitting the items one at a time.](http://reactivex.io/documentation/operators/buffer.html)
	 * 
	 * **Timing**:
	 * - **Spec**:
	 *   - Any values produced by the original Producer after a call to `buffer` must be included in the return value. (`clone $this` before `await`)
	 *   - Any number of values produced after the `$signal` has ticked may be included in that buffer, if they arrive in the ready queue before the next `yield`ing arc continues.
	 * - **Preferred**:
	 *   - Values after the `$signal` has ticked must not be included in that buffer.
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
	 * [Only emit an item from an Observable if a particular timespan has passed without it emitting another item](http://reactivex.io/documentation/operators/debounce.html)
	 * 
	 * **Timing**:
	 * - **Spec**
	 *   - `$usecs := 0` may not emit all values from the original Producer in the return value.
	 *   - If the original Producer produces more than zero values, at least one value must be produced in the return value.
	 *   - The last value of the original Producer, if there is one, must be produced in the return value.
	 * @param $usecs - The "timespan" as described above, in microseconds.
	 */
	
	// public function debounce(int $usecs): this {
	// 	$clone = clone $this;
	// 	return new static(Vector{ ($appender) ==> {
	// 		foreach()
	// 	} })
	// }
	
	// public function debounce(int $usecs): this {
	// 	$clone = clone $this;
	// 	$extendable = new Wrapper(new Extendable($clone->next()));
	// 	return new static(($appender) ==> Vector{
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
	 * [periodically subdivide items from an Observable into Observable windows and emit these windows rather than emitting the items one at a time](http://reactivex.io/documentation/operators/window.html)
	 * 
	 * **Timing**:
	 * - **Spec**
	 * @param $signal - Produce a value whenever a new window opens.
	 * @return - Produce Producers that group values from the original into windows dictated by `$signal`.
	 */
	public function window(Producer<mixed> $signal): Producer<this> {
		$clone = clone $this;
		$signal_clone = clone $signal;
		return static::create_producer(async {
			$i = new Wrapper(0);
			do {
				yield static::create(async {
					$stashed_i = $i->get();
					foreach(clone $clone await as $v) {
						if($i->get() !== $stashed_i)
							// the window has closed/
							// note: we'll keep producing on this window if the windower stops producing
							return;
						yield $v;
					}
				});
				$i->v++;
				$next = await $signal_clone->next();
			}
			while(!is_null($next)); // a more naive stop condition, but much more expressive
			// while($signal_clone->is_producing()); // this is more technically correct, but for now there isn't any difference here, and the former is easier to debug
		});
	}
	
	/**
	 * [Emit the most recent items emitted by an Observable within periodic time intervals](http://reactivex.io/documentation/operators/sample.html)
	 * 
	 * **Timing**: {@see HHReactor\Collection\Producer::window()}
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
	
	public static function count_up(int $start = 0, int $step = 1): Producer<int> {
		return static::create_producer(async {
			for(;;$start += $step) {
				yield $start;
				await \HH\Asio\later();
			}
		});
	}
	
	// public final static function defer((function(): Producer<T>) $factory): this {
	// 	return static::create(new DeferredProducer($factory));
	// }
	// public static function empty(): this {
	// 	return static::create(new EmptyAsyncIterator());
	// }
	public static function throw(\Exception $e): this {
		return new static(async {
			throw $e;
		});
	}
	public static function interval(int $usecs): Producer<int> {
		return static::create_producer(async {
			for($i = 0; ; $i++) {
				await \HH\Asio\usleep($usecs);
				yield $i;
			}
		});
	}
	public static final function just(T $v): this {
		return static::create(async {
			yield $v;
		});
	}
	public static function range(int $n, int $m): Producer<int> {
		return static::create_producer(async {
			for(; $n < $m; $n++)
				yield $n;
		});
	}
	public static final function repeat(T $v, ?int $n = null): this {
		return static::create(async {
			for(; is_null($n) || $n > 0; !is_null($n) && $n--)
				yield $v;
		});
	}
	public static final function repeat_sequence(Traversable<T> $vs, ?int $n = null): this {
		return static::create(async {
			for(; is_null($n) || $n > 0; !is_null($n) && $n--)
				foreach($vs as $v)
					yield $v;
		});
	}
	public final static function timer(T $v, int $delay): this {
		return static::create(async {
			await \HH\Asio\usleep($delay);
			yield $v;
		});
	}
	public final static function from(Vector<Awaitable<T>> $subawaitables): this {
		return static::create(async {
			foreach($subawaitables as $v) {
				$v = await $v;
				yield $v;
			}
		});
	}
	public final static function merge(Vector<AsyncIterator<T>> $producers): this {
		return new static($producers->map(($producer) ==> ($_) ==> $producer));
	}
	/**
	 * [Combine the emissions of multiple Observables together via a specified function and emit single items for each combination based on the results of this function.](http://reactivex.io/documentation/operators/zip.html)
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
	public static function combine_latest<Tu, Tv, Tx>(Producer<Tu> $A, Producer<Tv> $B, (function(Tu, Tv): Tx) $combiner): Producer<Tx> {
		$latest = tuple(null, null);
		return new static(Vector{
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
	public static function join<Tu, Tv, Tx>(Producer<Tu> $A, Producer<Tv> $B, (function(Tu): Awaitable<mixed>) $A_timer, (function(Tv): Awaitable<mixed>) $B_timer, (function(Tu, Tv): Tx) $combiner): Producer<Tx> {
		// ..gggghhhhhaaah...
		$latest_timer = tuple(async {}, async {});
		$latest = tuple(null, null);
		return new static(
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