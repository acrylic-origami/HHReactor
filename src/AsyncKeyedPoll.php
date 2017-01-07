<?hh // strict
namespace HHRx;
use HHRx\Collection\KeyedContainerWrapper as KC;
class AsyncKeyedPoll<+Tk, +T> implements AsyncKeyedIterator<Tk, T> {
	private ConditionWaitHandle<(Tk, T)> $wait_handle;
	// ... not a _huge_ fan of public $total_awaitable
	protected Awaitable<void> $total_awaitable;
	public function __construct(KC<Tk, Awaitable<T>> $iter) {
		// convert AsyncKC of Awaitables to Awaitable<AsyncKC<...>> to Awaitable<void>
		$this->total_awaitable = async {
			// must be wrapped in async to make Awaitable<void> for ConditionWaitHandle
			
			// too bad inst_meth doesn't work on private methods
			await \HHRx\Asio\KCm($iter->mapWithKey((Tk $k, Awaitable<T> $awaitable) ==> $this->_bind($k, $awaitable)));
		};
		$this->wait_handle = ConditionWaitHandle::create((async{})->getWaitHandle()); // blank wait handle to avoid null checks later
	}
	private async function _bind(Tk $k, Awaitable<T> $awaitable): Awaitable<void> {
		// wrap Awaitables in another async that will trigger success of the finish line wait handle
		try {
			$v = await $awaitable;
			$this->wait_handle->succeed(tuple($k, $v));
		}
		catch(\Exception $e) $this->wait_handle->fail($e);
	}
	protected function _restart_race(): void {
		$this->wait_handle = ConditionWaitHandle::create($this->total_awaitable->getWaitHandle());
	}
	public function extend(Awaitable<void> $incoming): void {
		$this->total_awaitable = async { await \HH\Asio\v(Vector{ $this->total_awaitable, $incoming }); };
	}
	public async function next(): Awaitable<(Tk, T)> {
		$this->_restart_race();
		return await $this->wait_handle;
	}
}