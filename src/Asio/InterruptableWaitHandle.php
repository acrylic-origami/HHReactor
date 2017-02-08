<?hh // strict
namespace HHReactor\Asio;
class InterruptableWaitHandle<T> implements Awaitable<T> {
	private ResettableConditionWaitHandle<T> $handle;
	public function __construct(Awaitable<T> $awaitable) {
		$this->handle = new ResettableConditionWaitHandle(\HH\Asio\later()->getWaitHandle()); // dummy Awaitable if $awaitable is already finished
		$notifier = async {
			$v = await $awaitable;
			$handle = $this->handle;
			if(!$handle->getWaitHandle()->isFinished())
				// if we weren't beat to the punch by a notification
				await $handle->succeed($v);
		};
		if(!$awaitable->getWaitHandle()->isFinished()) {
			$this->handle = new ResettableConditionWaitHandle($notifier->getWaitHandle());
		}
	}
	public function getWaitHandle(): WaitHandle<T> {
		return $this->handle->getWaitHandle();
	}
	public async function succeed(T $v): Awaitable<void> {
		await $this->handle->succeed($v);
	}
	public async function fail(\Exception $e): Awaitable<void> {
		await $this->handle->fail($e);
	}
	public function isFinished(): void {
		$this->handle->getWaitHandle()->isFinished();
	}
}