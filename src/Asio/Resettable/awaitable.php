<?hh // strict
namespace HHReactor\Asio\Resettable;
use HHReactor\Wrapper;
use HHReactor\CovWrapper;
use function HHReactor\Asio\voidify;
function awaitable<T>((function((function(): void)): Awaitable<T>) $resetter): (Awaitable<T>, CovWrapper<Awaitable<mixed>>) {
	$condition_wrapper = new Wrapper(null);
	$dependency_wrapper = new Wrapper(null);
	$reset = () ==> {
		$condition = $condition_wrapper->get();
		if(!is_null($condition) && $condition instanceof ConditionWaitHandle && !$condition->isFinished()) {
			$condition->succeed(null);
			$dependency = $dependency_wrapper->get();
			invariant(!is_null($dependency), 'Cannot be null: set before $condition.');
			$condition_wrapper->set(ConditionWaitHandle::create($dependency));
		}
	};
	$dependency = $resetter($reset);
	$dependency_wrapper->set($dependency);
	try {
		$condition_wrapper->set(ConditionWaitHandle::create(voidify($dependency)));
		/* HH_IGNORE_ERROR[4110] $condition_wrapper provably does not wrap null */
		return tuple($dependency, $condition_wrapper);
	}
	catch(\InvalidArgumentException $e) {
		if($e->getMessage() != 'ConditionWaitHandle not notified by its child.')
			throw $e;
	}
	return tuple($dependency, new Wrapper($dependency)); // second $dependency in the Wrapper is a bit of a hack -- it doesn't mean much, other than the fact it's completed
}