<?hh // strict
namespace HHReactor\Asio;
type Status = shape('succeeded' => bool, 'failed' => bool, 'finished' => bool);
class Dependencies<T> extends ConstDependencies<T> {
	// Assume failed || succeeded <=> finished
	public function depend<Tv as T>(Awaitable<Tv> $dependency): Awaitable<Tv> {
		$this->dependencies->set(spl_object_hash($dependency), $dependency);
		$this->all = shape(
			'succeeded' => $this->all['succeeded'] && $dependency->getWaitHandle()->isSucceeded(),
			'failed' => $this->all['failed'] && $dependency->getWaitHandle()->isFailed(),
			'finished' => $this->all['finished'] && $dependency->getWaitHandle()->isFinished()
		);
		return $dependency;
	}
}