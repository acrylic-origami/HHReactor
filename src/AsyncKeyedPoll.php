<?hh // strict
namespace HHRx;

use HHRx\Collection\AsyncKeyedContainerWrapper as AsyncKC;
use HHRx\Collection\ExactKeyedContainerWrapper as ExactKC;
use HHRx\Collection\AsyncKeyedIteratorWrapper;
use HHRx\Collection\AsyncMutableKeyedContainerWrapper as AsyncMutableKC;
use HHRx\Collection\PairwiseKeyedContainerWrapper as PairwiseKC;
class AsyncKeyedPoll<+Tk, +T> implements AsyncKeyedIterator<Tk, T> {
	private ?ConditionWaitHandle<(Tk, T)> $race_handle = null;
	private Vector<(Tk, T)> $stash = Vector{}; // for degenerative case
	private int $stash_pointer = 0;
	protected WaitHandle<void> $total_awaitable_handle;
	private Map<Tk, Awaitable<T>> $subawaitables;
	public function __construct(KeyedIterable<Tk, Awaitable<T>> $subawaitables) {
		// convert AsyncKC of Awaitables to Awaitable<AsyncKC<...>> to Awaitable<void>
		$subawaitables = new Map($subawaitables); // MUST convert to Map so that filtering preserves keys (see especially AsyncKeyedPoll::merge)
		$this->subawaitables = 
			$subawaitables->filterWithKey((Tk $k, Awaitable<T> $subawaitable) ==> $this->_subawaitable_filter($k, $subawaitable));
		$bound_subawaitables = $this->subawaitables->mapWithKey((Tk $k, Awaitable<T> $subawaitable) ==> $this->_bind($k, $subawaitable));
			              
		$total_awaitable = async {
			await \HH\Asio\m($bound_subawaitables);
		};
		$this->total_awaitable_handle = $total_awaitable->getWaitHandle();
		if(!$this->total_awaitable_handle->isFinished())
			// Awaitable<Map<Tk, void>> -> create(Awaitable<void>)
			$this->race_handle = ConditionWaitHandle::create($this->total_awaitable_handle);
	}
	private function _subawaitable_filter(Tk $k, Awaitable<T> $subawaitable): bool {
		$wait_handle = $subawaitable->getWaitHandle();
		if($wait_handle->isFinished())
			$this->stash->add(tuple($k, $wait_handle->result()));
		return !$wait_handle->isFinished();
	}
	public function filter_map((function(Tk, Awaitable<T>): ?Awaitable<T>) $f): AsyncKeyedPoll<Tk, T> {
		$M = Map{};
		foreach($this->subawaitables as $k => $awaitable) {
			$mapped = $f($k, $awaitable);
			if(!is_null($mapped))
				$M->set($k, $mapped);
		}
		return new self($M);
	}
	/* HH_IGNORE_ERROR[4110] $race_handle->fail is guaranteed to  */
	private async function _bind(Tk $k, Awaitable<T> $awaitable): Awaitable<void> {
		// wrap Awaitables in another async that will trigger success of the finish line wait handle
		try {
			$v = await $awaitable;
			$race_handle = $this->race_handle;
			invariant(!is_null($race_handle), '_bind can only be called when there exists at least one pending `Awaitable` in the list.');
			$race_handle->succeed(tuple($k, $v));
		}
		catch(\Exception $e) {
			$race_handle = $this->race_handle;
			invariant(!is_null($race_handle), '_bind can only be called when there exists at least one pending `Awaitable` in the list.');
			$race_handle->fail($e);
		}
	}
	private function get_subawaitables(): \ConstMap<Tk, Awaitable<T>> {
		return $this->subawaitables;
	}
	public async function next(): Awaitable<?(Tk, T)> {
		$race_handle = $this->race_handle;
		if(is_null($race_handle)) {
			// degenerative case: pull from stash non-async
			if($this->stash_pointer < $this->stash->count())
				return $this->stash[$this->stash_pointer++];
			else
				return null;
		}
		else {
			$next = await $this->race_handle;
			if(!$this->total_awaitable_handle->isFinished())
				// ConditionWaitHandle::create can't take finished Awaitables, so if this is the last element, don't reset the race.
				$this->race_handle = ConditionWaitHandle::create($this->total_awaitable_handle);
			return $next;
		}
	}
	public function keyed_omit((function(Tk): bool) $f): AsyncKeyedPoll<Tk, T> {
		return new self($this->get_subawaitables()->filterWithKey((Tk $k, Awaitable<T> $_) ==> $f($k)));
	}
	public static function merge<Tx, Tv>(Iterable<AsyncKeyedPoll<Tx, Tv>> $incoming): AsyncKeyedPoll<Tx, Tv> {
		$pairwise = Vector{};
		foreach($incoming as $poller) {
			$stripped_awaitables = 
				$poller->get_subawaitables()->items();
			          // ->filter((Awaitable<Tv> $v) ==> !$v->getWaitHandle()->isFinished())
			          // ->mapWithKey((Tx $k, Awaitable<Tv> $v) ==> Pair{$k, $v}); // -> Traversable<Pair<Tx, Tv>> as to not clobber overloaded keys (e.g. with Map{})
			// var_dump($poller->get_subawaitables());
			$pairwise = $pairwise->concat($stripped_awaitables);
		}
		return new self(new PairwiseKC($pairwise));
	}
}