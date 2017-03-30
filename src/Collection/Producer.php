<?hh // strict
namespace HHReactor\Collection;
use HHReactor\Asio\{
	ResettableConditionWaitHandle,
	Haltable,
	Extendable,
	ExtendableLifetime,
	HaltResult,
	DelayedEmptyAsyncIterator
};
use HHReactor\Wrapper;
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
class Producer<+T> extends EmitIterator<T> {
	// MUST CLONE TO SEPARATE POINTERS
	public function __construct(AsyncIterator<T> $iterator) {
		parent::__construct(self::_iterators_to_emitters(Vector{ $iterator })); // in the lack of constructor overloading
	}
	
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
		return new self(async {
			foreach(clone $this await as $v)
				yield $f($v);
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
		return new static(new EmitIterator(
			($emittee) ==> Vector{ async {
				foreach($clone await as $seed) {
					$subclone = clone $meta($seed);
					yield async {
						foreach($subclone await as $v)
							$emittee($v);
					};
				}
			}
		}));
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
		return new static(new EmitIterator(
			($emittee) ==> Vector{ async {
				$current_idx = new Wrapper(-1);
				foreach($clone await as $seed) {
					$i = $current_idx->get();
					$current_idx->set(++$i);
					
					$subclone = clone $meta($seed);
					yield async {
						foreach($subclone await as $v)
							if($current_idx->get() === $i) // eh, not the most performant way... but meh, until it becomes a problem
								$emittee($v);
					};
				}
			}
		}));
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
	public function group_by<Tk as arraykey>((function(T): Tk) $keysmith): Producer<this> {
		$clone = clone $this;
		$emittees = Map{};
		return new static(EmitIterator::create_by_unicasting_emittee(
			async ($emittee) ==> {
				foreach($clone await as $v) {
					$key = $keysmith($v);
					if(!$emittees->containsKey($key))
						// add emittee
						$emittee(new static(EmitIterator::create_by_unicasting_emittee(async ($subemittee) ==> $emittees->set($key, $subemittee))));
					$emittees[$key]($v);
				}
			}
		));
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
		return new self(async {
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
	public function debounce(int $usecs): this {
		$clone = clone $this;
		$extendable = new Wrapper(new Extendable($clone->next()));
		return new static(new EmitIterator((Emittee<T> $emittee) ==> Vector {
			async {
				$first_vec = await $extendable->get();
				$k_v = $first_vec->lastValue();
				yield async {
					while(!is_null($k_v)) {
						$delayed_k_v = self::_delay_return($usecs, $k_v);
						if($extendable->get()->getWaitHandle()->isFinished())
							$extendable->set(new Extendable($delayed_k_v));
						else
							$extendable->get()->extend($delayed_k_v);
						$k_v = await $clone->next();
					}
				};
				// ($k_v == null) \implies (\HH\Asio\has_finished($clone))
				while(!\HH\Asio\has_finished($clone->get_lifetime())) {
					$V = await $extendable->get();
					$last = $V->lastValue();
					if(!is_null($last))
						$emittee($last[1]);
				}
			}
		}));
	}
	
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
		return new static(async {
			while(!$signal->is_finished() && !$clone->is_finished()) {
				$window = EmitIterator::create_from_iterators(
					Vector{ $clone },
					(Iterable<Awaitable<mixed>> $total) ==>
						Producer::from_nonblocking($total->concat(Vector{ $signal->next() }))
				              ->first() // race the next signal tick and the bulk emitter total
         	);
         	yield new static($window);
         	await $window->get_lifetime();
			}
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
		return new static(EmitIterator::create_from_awaitables($subawaitables));
	}
	public final static function from(Iterable<Awaitable<T>> $subawaitables): this {
		return new static(async {
			foreach($subawaitables as $v) {
				$v = await $v;
				yield $v;
			}
		});
	}
	public final static function merge(Iterable<this> $producers): this {
		return new static(EmitIterator::create_from_iterators($producers));
	}
	/**
	 * [Combine the emissions of multiple Observables together via a specified function and emit single items for each combination based on the results of this function.](http://reactivex.io/documentation/operators/zip.html)
	 * @param $A - Producer of left-hand items
	 * @param $B - Producer of right-hand items
	 * @param $combiner - Zips left- and right-hand items to `Tx`-valued items.
	 */
	public static function zip<Tu, Tv, Tx>(Producer<Tu> $A, Producer<Tv> $B, (function(Tu, Tv): Tx) $combiner): Producer<Tx> {
		return new self(async {
			while(true) {
				// requires HHVM ^3.16
				list($u, $v) = await \HH\Asio\va($A->next(), $B->next());
				if(is_null($u) || is_null($v)) //  || $u[1]['_halted'] || $v[1]['_halted']
					break;
				// /* HH_IGNORE_ERROR[4110] Because neither are halted, one could be null because the corresponding `Tu` or `Tv` is nullable, or neither is null. */
				yield $combiner($u[1], $v[1]);
			}
		});
	}
	public static function combine_latest<Tu, Tv, Tx>(Producer<Tu> $A, Producer<Tv> $B, (function(Tu, Tv): Tx) $combiner): Producer<Tx> {
		$latest = tuple(null, null);
		return new self(EmitIterator::create_from_iterators(Vector{
			async {
				foreach(clone $A await as $v) {
					$latest[0] = $v;
					list($u, $v) = $latest;
					if(!is_null($u) && !is_null($v))
						yield $combiner($u, $v);
				}
			}, async {
				foreach(clone $B await as $v) {
					$latest[1] = $v;
					list($u, $v) = $latest;
					if(!is_null($u) && !is_null($v))
						yield $combiner($u, $v);
				}
			}
		}));
	}
	public static function join<Tu, Tv, Tx>(Producer<Tu> $A, Producer<Tv> $B, (function(Tu): Awaitable<mixed>) $A_timer, (function(Tv): Awaitable<mixed>) $B_timer, (function(Tu, Tv): Tx) $combiner): Producer<Tx> {
		// ..gggghhhhhaaah...
		$latest_timer = tuple(async {}, async {});
		$latest = tuple(null, null);
		return new static(new EmitIterator(
			($emittee) ==> Vector{ 
				async {
					foreach(clone $A await as $u) {
						$latest_timer[0] = $A_timer($u);
						$latest[0] = $u;
						
						$v = $latest[1];
						yield $latest_timer[0];
						if(!is_null($v) && !\HH\Asio\has_finished($latest_timer[1]))
							$emittee($combiner($u, $v));
					}
				},
				async {
					foreach(clone $B await as $v) {
						$latest_timer[1] = $B_timer($v);
						$latest[1] = $v;
						
						$u = $latest[0];
						yield $latest_timer[1];
						if(!is_null($u) && !\HH\Asio\has_finished($latest_timer[0]))
							$emittee($combiner($u, $v));
					}
				}
			}
		));
	}
	
	private static async function _delay_return<Tv>(int $usecs, Tv $v): Awaitable<Tv> {
		await \HH\Asio\usleep($usecs);
		return $v;
	}
}