<?hh // strict
namespace HHRx\Asio;
class ConditionWaitHandleWrapper<T> implements Awaitable<T> { // extends Wrapper<ConditionWaitHandle<T>>
	private ?ConditionWaitHandle<T> $wait_handle = null;
	public function __construct(private ?WaitHandle<void> $total_wait_handle = null) {
		$total_wait_handle = $this->total_wait_handle;
		if(!is_null($total_wait_handle))
			$this->wait_handle = ConditionWaitHandle::create($total_wait_handle);
	}
	public function set(WaitHandle<void> $total_wait_handle): void {
		$this->total_wait_handle = $total_wait_handle;
		$this->wait_handle = ConditionWaitHandle::create($total_wait_handle);
	}
	public function reset(): void {
		$total_wait_handle = $this->total_wait_handle;
		if(!is_null($total_wait_handle))
			$this->wait_handle = ConditionWaitHandle::create($total_wait_handle);
	}
	private function fn(): void {}
	private async function _notify((function(ConditionWaitHandle<T>): void) $f): Awaitable<void> {
		while(true) {
			$wait_handle = $this->wait_handle;
			$total_wait_handle = $this->total_wait_handle;
			invariant(!is_null($wait_handle), '');
			invariant(!is_null($total_wait_handle), '`total_wait_handle` cannot be null if `$wait_handle` is not null by construction.');
			if(!$wait_handle->isFinished() || $total_wait_handle->isFinished())
				break;
			await \HH\Asio\later(); // for resetting operations: it's possible for two `succeed` to be queued before the reset is executed, so keep pushing this back until we have an unfinished handle to notify
		}
		$wait_handle = $this->wait_handle;
		invariant(!is_null($wait_handle), '`_notify` called before assignment of `ConditionWaitHandle` or reset operation has completed.');
		$f($wait_handle);
		await \HH\Asio\later(); // ensure that control is released immediately to the handlers subscribed on this ConditionWaitHandle
	}
	public function succeed(T $v): Awaitable<void> {
		return $this->_notify((ConditionWaitHandle<T> $wait_handle) ==> {
			$wait_handle->succeed($v);
		});
	}
	public function fail(\Exception $e): Awaitable<void> {
		return $this->_notify((ConditionWaitHandle<T> $wait_handle) ==> {
			$wait_handle->fail($e);
		});
	}
	public function getWaitHandle(): ConditionWaitHandle<T> {
		$wait_handle = $this->wait_handle;
		invariant(!is_null($wait_handle), 'Tried to `await` empty ConditionWaitHandleWrapper.');
		return $wait_handle;
	}
	public static function create<Tx>(WaitHandle<void> $handle): ConditionWaitHandleWrapper<Tx> {
		return new self($handle);
	}
}