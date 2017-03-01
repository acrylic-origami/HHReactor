<?hh // strict
namespace HHReactor\Collection;
use HHReactor\Asio\ExtendableLifetime;
/**
 * Analogous to AsyncIterators via async block + `yield`, merged together. Greater control of lifetime by {@see HHReactor\Collection\EmitIterator::reducer}. No emitters can be added after construction.
 * 
 * Outside of lifetime control, this is implementable with {@see HHReactor\Collection\Producer} and its {@see HHReactor\Collection\Producer::merge()}.
 */
class EmitIterator<+T> implements AsyncIterator<T> {
	private ConditionWaitHandle<mixed> $bell;
	private ExtendableLifetime $total_awaitable;
	private Queue<T> $lag;
	/**
	 * Create lifetime for the Iterator by transforming a collection of "emitters" by some reducing function.
	 * @param $emitters - Maybe call the `EmitTrigger` to broadcast a value eventually to consumers of this EmitIterators.
	 * @param $reducer - Transform the `$emitters` into an Awaitable.
	 * @param $sidechain - Any Awaitables that do not emit items but affect the `$emitters` through shared scope to be `await`ed within the lifetime.
	 */
	public function __construct(
		Vector<(function(EmitTrigger<T>): Awaitable<mixed>)> $emitters,
		(function(Vector<Awaitable<mixed>>): Awaitable<mixed>) $reducer = fun('\HH\Asio\v'),
		Vector<Awaitable<mixed>> $sidechain = Vector{}
	) {
		$trigger = (T $v) ==> $this->_emit($v); // publicize `_emit` trigger for emitters
		$this->total_awaitable = new ExtendableLifetime(async {
			await \HH\Asio\later();
			await $reducer(
				$emitters->map(($emitter) ==> $emitter($trigger))
                     ->concat($sidechain)
			);
		});
		$this->bell = ConditionWaitHandle::create($this->total_awaitable->getWaitHandle());
		$this->lag = new Queue();
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
		if(!$this->bell->isFinished())
			$this->bell->succeed(null);
		$this->lag->add($v);
	}
	
	/**
	 * Add functionality to sidechain.
	 * 
	 * **Timing**:
	 * - Depends heavily on {@see HHReactor\Asio\Extendable::soft_extend()}
	 * - **Predicted**
	 *   - The incoming `Awaitable` may not be `await`ed on the next arc -- any number of arcs may enter the ready queue beforehand. However, it must be `await`ed eventually.
	 * 
	 * **Sharing**: Unisolated. Adding to the sidechain of one EmitIterator adds to the sidechain (and hence lifetimes) of all EmitIterators derived from it.
	 * @param $incoming - `Awaitable` to add to the sidechain
	 */
	public function sidechain(Awaitable<void> $incoming): void {
		$this->total_awaitable->soft_extend($incoming);
	}
	/**
	 * Create an `Awaitable` representing the next element at time of call.
	 * 
	 * **Timing**:
	 * - **Predicted**
	 *   - Any number of elements may be queued and returned in ready-wait fashion; that is, without returning control to the top level during iteration with `await as`, even if they are not produced in ready-wait fashion.
	 */
	public async function next(): Awaitable<?(mixed, T)> {
		if($this->lag->is_empty()) {
			if($this->bell->isFinished())
				// idempotent reset
				if(!\HH\Asio\has_finished($this->total_awaitable))
					$this->bell = ConditionWaitHandle::create($this->total_awaitable->getWaitHandle());
				else
					return null;
			
			await $this->bell;
		}
		return tuple(null, $this->lag->shift());
	}
}