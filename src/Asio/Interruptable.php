<?hh // strict
namespace HHReactor\Asio;
class Interruptable<T> implements Awaitable<T> {
	private ConditionWaitHandle<T> $handle;
	public function __construct(Awaitable<T> $awaitable) {
		$this->handle = ConditionWaitHandle::create(\HH\Asio\later()->getWaitHandle()); // dummy Awaitable if $awaitable is already finished
		$notifier = async {
			$v = await $awaitable;
			$handle = $this->handle;
			if(!$handle->isFinished())
				// if we weren't beat to the punch by a notification
				$handle->succeed($v);
		};
		if(!$awaitable->getWaitHandle()->isFinished()) {
			$this->handle = ConditionWaitHandle::create($notifier->getWaitHandle());
		}
	}
	public function getWaitHandle(): WaitHandle<T> {
		return $this->handle;
	}
	public function succeed(T $v): void {
		$this->handle->succeed($v);
	}
	public function fail(\Exception $e): void {
		$this->handle->fail($e);
	}
}