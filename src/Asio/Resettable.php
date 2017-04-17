<?hh // strict
namespace HHReactor\Asio;
use HHReactor\Wrapper;
use HHReactor\CovWrapper;
class Resettable {
	private Wrapper<ConditionWaitHandle<mixed>> $condition_wrapper;
	private WaitHandle<void> $dependency;
	public function __construct((function((function(): void)): Awaitable<mixed>) $resetter) {
		$this->dependency = voidify($resetter(() ==> $this->_reset()))->getWaitHandle();
		$this->condition_wrapper = new Wrapper(ConditionWaitHandle::create($this->dependency));
	}
	private function _reset(): void {
		$condition_wrapper = $this->condition_wrapper;
		if(!is_null($condition_wrapper)) {
			$condition = $condition_wrapper->get();
			if($condition instanceof ConditionWaitHandle && !$condition->isFinished()) {
				$condition->succeed(null);
				$condition_wrapper->set(ConditionWaitHandle::create($this->dependency));
			}
		}
	}
	public static function create((function((function(): void)): Awaitable<mixed>) $resetter): (Awaitable<mixed>, CovWrapper<Awaitable<mixed>>) {
		try {
			$self = new self($resetter);
			return tuple($self->dependency, $self->condition_wrapper);
		}
		catch(\InvalidArgumentException $e) {
			if($e->getMessage() != 'ConditionWaitHandle not notified by its child.')
				throw $e;
		}
		return tuple(async{}, new Wrapper(async {}));
	}
	
	// this whole method wouldn't be necessary if iterators were polymorphic with Awaitable
	public static function create_from_iterator<T>((function((function(): void)): AsyncIterator<T>) $resetter): (AsyncIterator<T>, CovWrapper<Awaitable<mixed>>) {
		try {
			$self = new self($resetter);
			return tuple($self->dependency, $self->condition_wrapper);
		}
		catch(\InvalidArgumentException $e) {
			if($e->getMessage() != 'ConditionWaitHandle not notified by its child.')
				throw $e;
		}
		return tuple(new DelayedEmptyAsyncIterator(async{}), new Wrapper(async {}));
	}
}