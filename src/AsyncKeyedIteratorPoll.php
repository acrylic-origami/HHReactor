<?hh // strict
namespace HHRx;

use HHRx\Collection\AsyncMapW;
use HHRx\Collection\VectorW;
use HHRx\Collection\KeyedProducer;
use HHRx\Collection\KeyedContainerWrapper as KC;
use HHRx\Collection\AsyncKeyedIteratorWrapper;
use HHRx\Collection\ExactKeyedContainerWrapper as ExactKC;
use HHRx\Collection\AsyncKeyedContainerWrapper as AsyncKC;
use HHRx\Collection\MutableKeyedContainerWrapper as MutableKC;
class AsyncKeyedIteratorPoll<+Tk, +T> implements AsyncKeyedIterator<Tk, T> { // extends AsyncKeyedPoll<Tk, T>?
	private AsyncKeyedPoll<int, ?(Tk, T)> $poller;
	public function __construct(private KeyedContainer<int, KeyedProducer<Tk, T>> $producers) {
		// convert AsyncKC of Awaitables to Awaitable<AsyncKC<...>> to Awaitable<void>
		
		// Container of KeyedIterators -> KC<arraykey, Awaitable<(Tk, T)>>
		$subawaitables = Vector{};
		foreach($this->producers as $producer) {
			$subawaitables->add($producer->next());
		}
		echo $subawaitables->count();
		// KC<arraykey, Awaitable<(Tk, T)>> -> AsyncKC<arraykey, (Tk, T)>
		$this->poller = new AsyncKeyedPoll($subawaitables);
	}
	public function get_iterators(): KeyedContainer<int, KeyedProducer<Tk, T>> {
		// just because we can
		return $this->producers;
	}
	public async function next(): Awaitable<?(Tk, T)> {
		// echo 'CALL';
		foreach($this->poller await as $k => $poller_next) {
			// var_dump($poller_next);
			if(!is_null($poller_next)) {
				$next = $this->producers[$k]->next();
				$next_wait_handle = $next->getWaitHandle();
				if(!$next_wait_handle->isFinished() || !is_null($next_wait_handle->result())) {
				// var_dump($this->producers[$k]->get_stash());
				// var_dump($this->producers[$k]->pointer);
					$this->poller = AsyncKeyedPoll::merge(Vector{ 
						$this->poller->keyed_omit((int $incoming) ==> $incoming !== $k), 
						new AsyncKeyedPoll(Map{ $k => $next })
					});
				}
				// var_dump($this->poller);
				return $poller_next;
			}
		}
		return null;
	}
}