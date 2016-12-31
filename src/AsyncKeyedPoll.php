<?hh // strict
namespace HHRx;
use HHRx\Util\Collection\KeyedContainerWrapper as KC;
use HHRx\Util\Collection\AsyncKeyedContainerWrapper as AsyncKC;
class AsyncKeyedPoll<+Tk, +T> implements AsyncKeyedIterator<Tk, T> {
	private ConditionWaitHandle<(Tk, T)> $wait_handle;
	// ... not a _huge_ fan of public $total_awaitable
	protected Awaitable<void> $total_awaitable;
	public function __construct(AsyncKC<Tk, T> $iter) {
		// convert AsyncKC of Awaitables to Awaitable<AsyncKC<...>> to Awaitable<void>
		$this->total_awaitable = async {
			// must be wrapped in async to make Awaitable<void> for ConditionWaitHandle
			
			// too bad inst_meth doesn't work on private methods
			await $iter->async_keyed_map((Pair<Tk, Awaitable<T>> $p) ==> $this->_bind($p))->KCm();
		};
		$this->wait_handle = ConditionWaitHandle::create((async{})->getWaitHandle()); // blank wait handle to avoid null checks later
	}
	private async function _bind(Pair<Tk, Awaitable<T>> $p): Awaitable<void> {
		// wrap Awaitables in another async that will trigger success of the finish line wait handle
		list($k, $awaitable) = $p;
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