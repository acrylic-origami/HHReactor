<?hh // strict
namespace HHReactor\Asio;
type Status = shape('succeeded' => bool, 'failed' => bool, 'finished' => bool);
class Dependencies<T> extends ConstDependencies<T> {
	// Assume failed || succeeded <=> finished
	public function depend<Tv as T>(Awaitable<Tv> $dependency): Awaitable<Tv> {
		$this->dependencies->set(spl_object_hash($dependency), $dependency);
		try {
			if(\HH\Asio\has_finished($dependency->getWaitHandle()))
				\HH\Asio\result($dependency->getWaitHandle());
			
			$this->all = shape(
				'succeeded' => $this->all['succeeded'] && \HH\Asio\has_finished($dependency->getWaitHandle()),
				'failed' => false,
				'finished' => $this->all['finished'] && \HH\Asio\has_finished($dependency->getWaitHandle())
			);
		}
		catch(\Exception $_) {
			$this->all = shape(
				'succeeded' => false,
				'failed' => $this->all['failed'],
				'finished' => $this->all['finished'] && \HH\Asio\has_finished($dependency->getWaitHandle())
			);
		}
		return $dependency;
	}
}