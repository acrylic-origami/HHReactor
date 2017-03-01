<?hh // strict
namespace HHReactor\Collection;
use HHReactor\Asio\ExtendableLifetime;
/**
 * Analogous to AsyncIterators via async block + `yield`, merged together. Greater control of lifetime by {@see HHReactor\Collection\EmitIterator::reducer}.
 * 
 * The distinguishing factors from {@see HHReactor\Collection\Producer} and its {@see HHReactor\Collection\Producer::merge()} are:
 * 1. The ability to append in the future (from the scope of the original emitters),
 * 2. Control over the lifetime of the iterator.
 */
class EmitIterator<+T> implements AsyncIterator<T> {
	private ConditionWaitHandle<mixed> $bell;
	private ExtendableLifetime $total_awaitable;
	private Queue<T> $lag;
	/**
	 * Create lifetime for the Iterator by transforming a collection of "emitters" by some reducing function.
	 * @param $emitters - Maybe yield values, maybe add another emitter. These certainly may be `Awaitable`s in disguise wanting to be sidechained.
	 * @param $reducer - Transform the `$emitters` into an Awaitable.
	 * @param $sidechain - Any Awaitables that do not emit items but affect the `$emitters` through shared scope to be `await`ed within the lifetime.
	 */
	public function __construct(
		Vector<(function(EmitAppender<T>): AsyncIterator<T>)> $emitters,
		(function(Vector<Awaitable<mixed>>): Awaitable<mixed>) $reducer = fun('\HH\Asio\v')
	) {
		$appender = (AsyncIterator<T> $v) ==> $this->_append($v); // publicize `_append` for emitters
		$this->total_awaitable = new ExtendableLifetime(async {
			await \HH\Asio\later(); // guarantee bell is set
			await $reducer(
				$emitters->map(($emitter) ==> $this->_awaitify($emitter($appender)))
			);
		});
		$this->bell = ConditionWaitHandle::create($this->total_awaitable->getWaitHandle());
		$this->lag = new Queue();
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
		$this->total_awaitable->soft_extend($this->_awaitify($incoming));
	}
	
	/**
	 * Iterator -> Awaitable, emitting yielded values to the original iterator.
	 */
	private async function _awaitify(AsyncIterator<T> $incoming): Awaitable<void> {
		foreach($incoming await as $v)
			$this->_emit($v);
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