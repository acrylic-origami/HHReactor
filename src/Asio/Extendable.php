<?hh // strict
namespace HHReactor\Asio;
use HHReactor\Asio\Haltable;
use HHReactor\Collection\Producer;
/**
 * Allow T-typed `Awaitable`s to be added from any scope at any time, and eventually resolve to a time-ordered Vector of their results.
 */
class Extendable<T> {
	private Vector<Awaitable<T>> $extensions = Vector{};
	private ConditionWaitHandle<mixed> $condition;
	private WaitHandle<void> $dependency;
	protected function __construct((function((function(Awaitable<T>): void)): Awaitable<void>) $extender) {
		$this->dependency = $extender((Awaitable<T> $extension) ==> $this->_extend($extension))->getWaitHandle();
		$this->condition = ConditionWaitHandle::create($this->dependency);
	}
	private function _extend(Awaitable<T> $extension): void {
		$this->extensions->add($extension);
		if(!is_null($this->condition) && $this->condition instanceof ConditionWaitHandle && !$this->condition->isFinished()) {
			$this->condition->succeed(null);			
			$this->condition = ConditionWaitHandle::create($this->dependency);
		}
	}
	public static function create((function((function(Awaitable<T>): void)): Awaitable<void>) $extender): Awaitable<Vector<T>> {
		$self = null;
		try {
			$self = new self($extender);
		}
		catch(\InvalidArgumentException $e) {
			// extender Awaitable finishes during extender call
			if($e->getMessage() !== 'ConditionWaitHandle not notified by its child.')
				throw $e;
		}
		return async {
			invariant(!is_null($self), 'Cannot be null: set in `try`.');
			try {
				while(true)
					await $self->condition;
			}
			catch(\InvalidArgumentException $e) {
				if($e->getMessage() !== 'ConditionWaitHandle not notified by its child.')
					throw $e;
			}
			
			return await \HH\Asio\v($self->extensions);
		};
	}
}