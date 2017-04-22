<?hh // strict
namespace HHReactor\Asio;
use HHReactor\Asio\Haltable;
use HHReactor\Collection\Producer;
/**
 * Allow T-typed `Awaitable`s to be added from any scope at any time, and eventually resolve to a time-ordered Vector of their results.
 */
class Extendable<+T> implements Awaitable<\ConstVector<T>> {
	private Vector<Awaitable<T>> $extensions = Vector{};
	private ?ConditionWaitHandle<mixed> $condition = null;
	private WaitHandle<void> $dependency;
	protected function __construct((function((function(Awaitable<T>): void)): Awaitable<void>) $extender) {
		$this->dependency = $extender((Awaitable<T> $extension) ==> $this->_extend($extension))->getWaitHandle();
		try {
			$this->condition = ConditionWaitHandle::create($this->dependency);
		}
		catch(\InvalidArgumentException $e) {
			if($e->getMessage() !== 'ConditionWaitHandle not notified by its child')
				throw $e;
		}
	}
	private function _extend(Awaitable<T> $extension): void {
		$this->extensions->add($extension);
		$condition = $this->condition;
		if(!is_null($condition) && $condition instanceof ConditionWaitHandle && !$condition->isFinished()) {
			$condition->succeed(null);			
			$condition = ConditionWaitHandle::create($this->dependency);
		}
	}
	public function getWaitHandle(): WaitHandle<\ConstVector<T>> {
		return (async {
			try {
				while(true)
					await $this->condition;
			}
			catch(\InvalidArgumentException $e) {
				if($e->getMessage() !== 'ConditionWaitHandle not notified by its child')
					throw $e;
			}
			
			return await \HH\Asio\v($this->extensions);
		})->getWaitHandle();
	}
}