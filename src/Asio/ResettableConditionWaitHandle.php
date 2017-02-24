<?hh // strict
namespace HHReactor\Asio;
class ResettableConditionWaitHandle<T> implements Awaitable<T> { // extends Wrapper<ConditionWaitHandle<T>>
	protected ?ConditionWaitHandle<T> $wait_handle = null;
	public function __construct(private ?(function(): Awaitable<mixed>) $lifetime_factory = null) {
		if(!is_null($lifetime_factory))
			$this->wait_handle = ConditionWaitHandle::create(\HHReactor\Asio\voidify($lifetime_factory()));
	}
	public function set((function(): Awaitable<mixed>) $lifetime_factory): void {
		$this->lifetime_factory = $lifetime_factory;
		$this->wait_handle = ConditionWaitHandle::create(\HHReactor\Asio\voidify($lifetime_factory()));
	}
	private async function _notify((function(ConditionWaitHandle<T>): void) $f): Awaitable<void> {
		$wait_handle = $this->wait_handle;
		invariant(!is_null($wait_handle), 'Tried to notify ConditionWaitHandleWrapper before setting it.');
		
		while(true) {
			// if($total_wait_handle->isFinished() && !$total_wait_handle->isFailed()) return; // ?
			if(!$wait_handle->isFinished())
				break;
			elseif($wait_handle->isFailed())
				return;
			
			await \HH\Asio\later(); // for resetting operations: it's possible for two `succeed` to be queued before the reset is executed, so keep pushing this back until we have an unfinished handle to notify
		}
		$f($wait_handle);
		
		// RESET
		$factory = $this->lifetime_factory;
		invariant(!is_null($factory), 'The `wait_handle` invariant could not have passed without `lifetime_factory` being non-null.');
		$this->wait_handle = ConditionWaitHandle::create(\HHReactor\Asio\voidify($factory()));
		
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
}