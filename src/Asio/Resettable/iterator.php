<?hh // strict
namespace HHReactor\Asio\Resettable;
use HHReactor\Wrapper;
use HHReactor\CovWrapper;
use function HHReactor\Asio\voidify;
function iterator<T>((function((function(): void)): AsyncIterator<T>) $resetter): (AsyncIterator<T>, CovWrapper<Awaitable<mixed>>) {
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
	$iterator = $resetter($reset);
	$dependency = async { foreach($iterator await as $v) {} };
	$dependency_wrapper->set($dependency);
	try {
		$condition_wrapper->set(ConditionWaitHandle::create(voidify($dependency)));
		/* HH_IGNORE_ERROR[4110] Provably not null */
		return tuple($dependency, $condition_wrapper);
	}
	catch(\InvalidArgumentException $e) {
		if($e->getMessage() != 'ConditionWaitHandle not notified by its child.')
			throw $e;
	}
	return tuple($iterator, new Wrapper($dependency)); // second $dependency in the Wrapper is a bit of a hack -- it doesn't mean much, other than the fact it's completed
}