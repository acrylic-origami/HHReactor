<?hh // strict
namespace HHRx;
use HHRx\Collection\AsyncMapW;
use HHRx\Collection\VectorW;
use HHRx\Collection\KeyedContainerWrapper as KC;
use HHRx\Collection\ExactKeyedContainerWrapper as ExactKC;
use HHRx\Collection\AsyncKeyedContainerWrapper as AsyncKC;
use HHRx\Collection\MutableKeyedContainerWrapper as MutableKC;
class AsyncKeyedIteratorPoll<+Tk, +T> implements AsyncKeyedIterator<Tk, T> { // extends AsyncKeyedPoll<Tk, T>?
	private AsyncKeyedPoll<mixed, ?(Tk, T)> $poller;
	public function __construct(private ExactKC<mixed, AsyncKeyedIterator<Tk, T>> $producers) {
		// convert AsyncKC of Awaitables to Awaitable<AsyncKC<...>> to Awaitable<void>
		
		// Container of KeyedIterators -> KC<arraykey, Awaitable<(Tk, T)>>
		$subawaitables = $producers->map((AsyncKeyedIterator<Tk, T> $producer) ==>
			$producer->next());
		// KC<arraykey, Awaitable<(Tk, T)>> -> AsyncKC<arraykey, (Tk, T)>
		$this->poller = new AsyncKeyedPoll($subawaitables);
	}
	public function get_iterators(): ExactKC<mixed, AsyncKeyedIterator<Tk, T>> {
		// just because we can
		return $this->producers;
	}
	public async function next(): Awaitable<?(Tk, T)> {
		$poller_next = await $this->poller->next();
		if(!is_null($poller_next)) {
			// iteration isn't finished (Poller will return Awaitable<null> iff the totalawaitable is fully finished)
			$resolved_next = $poller_next[1];
			if(!is_null($resolved_next)) {
				// this iterator isn't finished: add the next item to the queue
				list($k, $_) = $resolved_next;
				$next = $this->producers->get_units()[$k]->next();
				$this->poller = AsyncKeyedPoll::merge(Vector{ 
					$this->poller, 
					new AsyncKeyedPoll(Vector{ $next })
				});
			}
			return $resolved_next;
		}
		else
			return null;
	}
}