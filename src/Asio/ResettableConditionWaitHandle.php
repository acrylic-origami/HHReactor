<?hh // strict
namespace HHReactor\Asio;
class ResettableConditionWaitHandle<T> extends ConditionWaitHandleWrapper<T> {
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
	public function get_total_wait_handle(): ?WaitHandle<void> {
		return $this->total_wait_handle;
	}
}