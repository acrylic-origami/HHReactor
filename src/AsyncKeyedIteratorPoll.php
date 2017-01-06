<?hh // strict
namespace HHRx;
use HHRx\Collection\KeyedContainerWrapper as KC;
use HHRx\Collection\AsyncKeyedContainerWrapper as AsyncKC;
class AsyncKeyedIteratorPoll<+Tk, +T> implements AsyncKeyedIterator<Tk, T> { // extends AsyncKeyedPoll<Tk, T>?
	private AsyncKeyedPoll<mixed, ?(Tk, T)> $poller;
	public function __construct(private KeyedContainer<mixed, AsyncKeyedIterator<Tk, T>> $iterators) {
		// convert AsyncKC of Awaitables to Awaitable<AsyncKC<...>> to Awaitable<void>
		
		// Container of KeyedIterators -> KC<arraykey, Awaitable<(Tk, T)>>
		$KC = new KC($iterators)->map((AsyncKeyedIterator<Tk, T> $iterator) ==> $iterator->next());
		// KC<arraykey, Awaitable<(Tk, T)>> -> AsyncKC<arraykey, (Tk, T)>
		$this->poller = new AsyncKeyedPoll(new AsyncKC($KC->get_units()));
	}
	public function get_iterators(): KeyedContainer<mixed, AsyncKeyedIterator<Tk, T>> {
		// just because we can
		return $this->iterators;
	}
	public async function next(): Awaitable<?(Tk, T)> {
		$ret = await $this->poller->next();
		$this->poller->extend(async { await $this->iterators[$ret[0]]->next(); }); // race condition maybe? I don't think so, because control doesn't return to the upper level to call next() to reset internal ConditionWaitHandle.
		return $ret[1];
	}
}