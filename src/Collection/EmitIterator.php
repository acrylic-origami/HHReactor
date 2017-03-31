<?hh // strict
namespace HHReactor\Collection;
use HHReactor\Asio\{
	Haltable,
	DelayedEmptyAsyncIterator,
	Extendable,
	Scheduler
};
/**
 * Analogous to AsyncIterators via async block + `yield`, merged together. Greater control of lifetime by {@see HHReactor\Collection\EmitIterator::reducer}.
 * 
 * The distinguishing factors from {@see HHReactor\Collection\Producer} and its {@see HHReactor\Collection\Producer::merge()} are:
 * 1. The ability to append in the future (from the scope of the original emitters),
 * 2. Control over the lifetime of the iterator.
 * 
 * Must obey the properties:
 * 1. `yield`ed Awaitables are awaited eventually
 * 2. All `emit`ted values are broadcasted to all listeners
 * 3. `emit`ted values are emitted in order
 */
class EmitIterator<+T> implements AsyncIterator<T> {
	private ConditionWaitHandle<mixed> $bell;
	private Extendable<mixed> $total_awaitable;
	private Iterable<Haltable<mixed>> $emitters = Vector{}; // Vector<Awaitable<T>> perhaps?
	private Queue<T> $lag;
	/**
	 * Create lifetime for the Iterator by transforming a collection of "emitters" by some reducing function.
	 * 
	 * **Timing**
	 * - **Spec**
	 *   - Must emit any ready-wait values from inputs in ready-wait fashion when iterated.
	 *     - These ready-wait values may be emitted in any order.
	 * @param $emitters - Maybe yield values. Maybe add another emitter. These certainly may be `Awaitable`s in disguise just wanting to be thrown on the scheduler.
	 * @param $reducer - Transform the `$emitters` into an Awaitable.
	 */
	protected function __construct(
		(function(Appender<T>): Iterable<AsyncIterator<T>>) $emitter_factory,
		(function(Iterable<Awaitable<mixed>>): Awaitable<mixed>) $reducer = fun('\HH\Asio\v')
	) {
		$this->lag = new Queue();
		
		$appender = ($v) ==> $this->_append($v);
		$this->total_awaitable = new Extendable(async {
			// assume $emitter stops emitting when the object reference is destroyed at the end of this async block
			await \HH\Asio\later(); // guarantee bell is set
			await $reducer($emitter_factory($appender)->map(($emitter) ==> $this->_awaitify($emitter)));
			// ring bell idempotently
			if(!$this->bell->isFinished())
				$this->bell->succeed(null);
		});
		$src = \HHReactor\Asio\voidify($this->total_awaitable->getWaitHandle());
		$this->bell = ConditionWaitHandle::create($src);
		// foreach($this->iterators as $iterator) {
		// 	$this->_pop_ready_wait_items($iterator);
		// }
	}
	
	private function _pop_ready_wait_items(AsyncIteratorWrapper<T> $iterator): void {
		// weed out ready items
		while(\HH\Asio\has_finished($next = $iterator->next())) {
			$concrete_next = $next->getWaitHandle()->result();
			if(!is_null($concrete_next)) {
				$this->lag->add($concrete_next[1]);
				printf('A%d', $concrete_next[1]);
			}
			else
				return;
		}
	}
	
	/**
	 * Add an emitter; merge the yielded values into the original iterator.
	 * 
	 * **Timing**:
	 * - All values yielded by the incoming `AsyncIterator` after this method call must be included in the original iterator, even if the incoming `AsyncIterator` is `await`ed elsewhere.
	 * - Values yielded by the incoming `AsyncIterator` may be yielded in the original `AsyncIterator` at any time in the future depending on the ready queue, and may be yielded in ready-wait fashion.
	 * - The relative order of items yielded by the incoming `AsyncIterator` must be preserved in the original iterator.
	 * @param $incoming - Emit zero or more values to be included in the original Iterator. This may certainly be an `Awaitable` wanting to be sidechained.
	 */
	private function _append(AsyncIterator<T> $incoming): void {
		$this->total_awaitable->extend($this->_awaitify($incoming));
	}
	
	/**
	 * Iterator -> Awaitable, emitting yielded values through the original `EmitIterator`.
	 */
	private async function _awaitify(AsyncIterator<T> $incoming): Awaitable<void> {
		$wrapped = new AsyncIteratorWrapper($incoming);
		$this->_pop_ready_wait_items($wrapped);
		foreach($wrapped await as $v) {
			if($this->total_awaitable->is_halted())
				// stop emitting items immediately if this EmitIterator has been halted
				// the GC might not be fast enough to 
				return;
			$this->_emit($v);
		}
	}
	
	/**
	 * Separate properties of cloned EmitIterators expected to be separate.
	 */
	public function __clone(): void {
		// Advance queue independently, but receive new, shared values
		$this->lag = clone $this->lag;
	}
	
	/**
	 * Broadcast the new value and ring the bell if it hasn't been rung recently
	 * @param $v - Value to be broadcasted
	 */
	private function _emit(T $v): void {
		// ring bell idempotently
		if(!$this->bell->isFinished())
			$this->bell->succeed(null);
		/* HH_FIXME[4110] */
		$this->lag->add($v.'a');
		
		var_dump($this->lag->toVector());
	}
	
	/**
	 * Add functionality to sidechain.
	 * 
	 * **Timing**:
	 * - Depends heavily on {@see HHReactor\Asio\Extendable::soft_extend()}
	 * - **Spec**
	 *   - The incoming `Awaitable` may not be `await`ed on the next arc -- any number of arcs may enter the ready queue beforehand. However, it must be `await`ed eventually.
	 * 
	 * **Sharing**: Unisolated. Adding to the sidechain of one EmitIterator adds to the sidechain (and hence lifetimes) of all EmitIterators derived from it.
	 * @param $incoming - `Awaitable` to add to the sidechain
	 */
	public function schedule(Awaitable<mixed> $incoming): void {
		$this->total_awaitable->extend($incoming);
	}
	/**
	 * Create an `Awaitable` representing the next element at time of call.
	 * 
	 * **Timing**:
	 * - **Spec**
	 *   - Any number of elements may be queued and returned in ready-wait fashion; that is, without returning control to the top level during iteration with `await as`, even if they are not produced in ready-wait fashion.
	 */
	public async function next(): Awaitable<?(mixed, T)> {
		if($this->lag->is_empty()) {
			if($this->bell->isFinished())
				// idempotent reset
				if(!$this->total_awaitable->getWaitHandle()->isFinished())
					$this->bell = ConditionWaitHandle::create(\HHReactor\Asio\voidify($this->total_awaitable->getWaitHandle()));
				else
					return null;
			
			await $this->bell;
		}
		if(!$this->lag->is_empty()) {
			// hphpd_break();
			$v = $this->lag->shift();
			var_dump($v);
			return tuple(null, $v);
		}
		else
			return null;
	}
	
	public function is_finished(): bool {
		return $this->lag->is_empty() && $this->total_awaitable->getWaitHandle()->isFinished();
	}
	
	/**
	 * Awaitable that must resolve at some point after the endpoint.
	 * 
	 * **Timing**:
	 * - **Spec**: The return value must not resolve faster than the Producer is `halt`ed, throws an Exception, or runs out of values. The return value may resolve at _any point_ afterwards, but must resolve eventually.
	 * 
	 * **Sharing**: Isolated. Lifetimes of cloned and derivative Producers must not affect the Timing clauses of each other.
	 */
	public function get_lifetime(): Awaitable<mixed> {
		return $this->total_awaitable;
	}
	
	/**
	 * Iterator to dispense values produced since the last call to {@see HHReactor\Collection\Producer::next()} or {@see HHReactor\Collection\Producer::fast_forward()}.
	 * 
	 * **Timing**: No known timing issues.
	 * 
	 * **Sharing**: Isolated. Fast-forwarding one Producer does not fast-forward cloned Producers.
	 */
	public function fast_forward(): Iterator<T> {
		// no risk of "Generator already started" or "Changed during iteration" exceptions, because there are no underlying core Hack collections in LinkedList iterables
		while(!$this->lag->is_empty()) {
			$next = $this->lag->shift();
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
	public function soft_halt(?\Exception $e = null): void {
		foreach($this->emitters as $emitter)
			$emitter->soft_halt($e);
		
		$this->total_awaitable->soft_halt($e);
		
		// ring bell idempotently
		if(!$this->bell->isFinished())
			$this->bell->succeed(null);
	}
	
	// public static function create_from_iterator<Tv>(AsyncIterator<Tv> $iterator): EmitIterator<Tv> {
	// 	return self::create_from_iterators(Vector{ $iterator });
	// }
	
	// public static function create_from_iterators<Tv>(
	// 	Iterable<AsyncIterator<Tv>> $iterators,
	// 	(function(Iterable<Awaitable<mixed>>): Awaitable<mixed>) $reducer = fun('\HH\Asio\v')): EmitIterator<Tv> {
	// 	return new self(($_) ==> $iterators, $reducer);
	// }
	
	public static function create_from_awaitable<Tv>(Awaitable<Tv> $awaitable): EmitIterator<Tv> {
		return self::create_from_awaitables(Vector{ $awaitable });
	}
	
	public static function create_from_awaitables<Tv>(Iterable<Awaitable<Tv>> $awaitables): EmitIterator<Tv> {
		return new self(
			($_) ==> $awaitables->map(async ($awaitable) ==> {
				$v = await $awaitable;
				yield $v;
			})
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
}