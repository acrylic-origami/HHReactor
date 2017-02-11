<?hh // strict
namespace HHReactor\Asio;
class InterruptableWaitHandle<T> implements Awaitable<T> {
	private ConditionWaitHandle<T> $handle;
	public function __construct(Awaitable<T> $awaitable) {
		$this->handle = ConditionWaitHandle::create(\HH\Asio\later()->getWaitHandle()); // dummy Awaitable if $awaitable is already finished
		$notifier = async {
			$v = await $awaitable;
			$handle = $this->handle;
			if(!$handle->getWaitHandle()->isFinished())
				// if we weren't beat to the punch by a notification
				$handle->succeed($v);
		};
		if(!$awaitable->getWaitHandle()->isFinished()) {
			$this->handle = ConditionWaitHandle::create($notifier->getWaitHandle());
		}
	}
	public function getWaitHandle(): WaitHandle<T> {
		return $this->handle->getWaitHandle();
	}
	public async function succeed(T $v): Awaitable<void> {
		$this->handle->succeed($v);
		await \HH\Asio\later();
	}
	public async function fail(\Exception $e): Awaitable<void> {
		$this->handle->fail($e);
		await \HH\Asio\later();
	}
	public function isFinished(): void {
		$this->handle->getWaitHandle()->isFinished();
	}
}