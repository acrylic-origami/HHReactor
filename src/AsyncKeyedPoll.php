<?hh // strict
namespace HHRx;
use HHRx\Collection\AsyncKeyedContainerWrapper as AsyncKC;
use HHRx\Collection\ExactKeyedContainerWrapper as ExactKC;
use HHRx\Collection\AsyncMutableKeyedContainerWrapper as AsyncMutableKC;
use HHRx\Collection\PairwiseKeyedContainerWrapper as PairwiseKC;
class AsyncKeyedPoll<+Tk, +T> implements AsyncKeyedIterator<Tk, T> {
	private ConditionWaitHandle<(Tk, T)> $race_handle;
	// ... not a _huge_ fan of public $total_awaitable
	protected WaitHandle<void> $total_awaitable_handle;
	private Vector<Pair<Tk, Awaitable<T>>> $subawaitables = Vector{};
	public function __construct(KeyedIterable<Tk, Awaitable<T>> $subawaitables) {
		// convert AsyncKC of Awaitables to Awaitable<AsyncKC<...>> to Awaitable<void>
		$total_awaitable = async {
			// must be wrapped in async to make Awaitable<void> for ConditionWaitHandle
			
			// too bad inst_meth doesn't work on private methods
			$M = Map{};
			foreach($subawaitables as $k => $awaitable) {
				$this->subawaitables->add(Pair{$k, $awaitable});
				$M->set($k, $this->_bind($k, $awaitable));
			}
			await \HH\Asio\m($M);
		};
		$this->total_awaitable_handle = $total_awaitable->getWaitHandle();
		$this->race_handle = ConditionWaitHandle::create($this->total_awaitable_handle);
	}
	private async function _bind(Tk $k, Awaitable<T> $awaitable): Awaitable<void> {
		// wrap Awaitables in another async that will trigger success of the finish line wait handle
		try {
			$v = await $awaitable;
			var_dump(Pair{$k, $v});
			$this->race_handle->succeed(tuple($k, $v));
		}
		catch(\Exception $e) {
			$this->race_handle->fail($e);
		}
	}
	private function get_subawaitables(): Vector<Pair<Tk, Awaitable<T>>> {
		return $this->subawaitables;
	}
	public async function next(): Awaitable<?(Tk, T)> {
		if($this->total_awaitable_handle->isFinished())
			// Even if the last awaitables complete while this code is running, the total awaitable won't resolve until control returns to the top level join and jumps to these awaitables which trigger the wait handle in turn. If this wait handle is finished, then iteration is truly finished.
			return null;
		$next = await $this->race_handle;
		if(!$this->total_awaitable_handle->isFinished())
			// ConditionWaitHandle::create can't take finished Awaitables, so if this is the last element, don't reset the race.
			$this->race_handle = ConditionWaitHandle::create($this->total_awaitable_handle);
		return $next;
	}
	public static function merge<Tx, Tv>(Iterable<AsyncKeyedPoll<Tx, Tv>> $incoming): AsyncKeyedPoll<Tx, Tv> {
		$pairwise = Vector{};
		foreach($incoming as $poller) {
			$stripped_awaitables = $poller->get_subawaitables()
			                              ->filter((Pair<Tx, Awaitable<Tv>> $k_v) ==> !$k_v[1]->getWaitHandle()->isFinished());
			$pairwise = $pairwise->concat($stripped_awaitables);
		}
		return new self(new PairwiseKC($pairwise));
	}
}