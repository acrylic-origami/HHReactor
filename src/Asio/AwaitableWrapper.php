<?hh // strict
namespace HHReactor\Asio;
abstract class AwaitableWrapper<+T, +TDepend as Awaitable<T>> implements Awaitable<T> {
	/* HH_FIXME[4120] The day object-protected comes to Hack... oh man. */
	protected function __construct(protected Awaitable<mixed> $dependency, protected TDepend $dependent) {}
	public function is_dependency_finished(): bool {
		return \HH\Asio\has_finished($this->dependency->getWaitHandle());
	}
	public function is_dependent_finished(): bool {
		return \HH\Asio\has_finished($this->dependent->getWaitHandle());
	}
	protected function get_dependent(): TDepend {
		return $this->dependent;
	}
	public function getWaitHandle(): WaitHandle<T> {
		return $this->dependent->getWaitHandle();
	}
}