<?hh // strict
namespace HHRx;
use HHRx\Collection\AsyncMapW;
use HHRx\Collection\KeyedContainerWrapper as KC;
use HHRx\Collection\WeakKeyedContainerWrapper as WeakKC;
use HHRx\Collection\AsyncKeyedContainerWrapper as AsyncKC;
use HHRx\Collection\MutableKeyedContainerWrapper as MutableKC;
class AsyncKeyedIteratorPoll<+Tk, +T> implements AsyncKeyedIterator<Tk, T> { // extends AsyncKeyedPoll<Tk, T>?
	private AsyncKeyedPoll<mixed, ?(Tk, T)> $poller;
	public function __construct(private WeakKC<mixed, AsyncKeyedIterator<Tk, T>> $iterators) {
		// convert AsyncKC of Awaitables to Awaitable<AsyncKC<...>> to Awaitable<void>
		
		// Container of KeyedIterators -> KC<arraykey, Awaitable<(Tk, T)>>
		$KC = $iterators->map((AsyncKeyedIterator<Tk, T> $iterator) ==> $iterator->next());
		// KC<arraykey, Awaitable<(Tk, T)>> -> AsyncKC<arraykey, (Tk, T)>
		$this->poller = new AsyncKeyedPoll($KC);
	}
	public function get_iterators(): WeakKC<mixed, AsyncKeyedIterator<Tk, T>> {
		// just because we can
		return $this->iterators;
	}
	public async function next(): Awaitable<?(Tk, T)> {
		list($k, $next) = await $this->poller->next();
		$this->poller->extend(async { await $this->iterators->get_units()[$k]->next(); }); // race condition maybe? I don't think so, because control doesn't return to the upper level to call next() to reset internal ConditionWaitHandle.
		return $next;
	}
}