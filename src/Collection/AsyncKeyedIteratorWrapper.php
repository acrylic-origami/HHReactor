<?hh // strict
namespace HHReactor\Collection;
class AsyncKeyedIteratorWrapper<+Tk, +Tv> implements AsyncKeyedIterator<Tk, Tv> {
	private ?Awaitable<?(Tk, Tv)> $handle = null;
	public function __construct(private AsyncKeyedIterator<Tk, Tv> $iterator) {}
	public function next(): Awaitable<?(Tk, Tv)> {
		$handle = $this->handle;
		if(is_null($handle) || $handle->getWaitHandle()->isFinished()) {
			// refresh the handle if this is the first `next` call or the underlying awaitable has resolved
			$this->handle = $this->iterator->next();
			return $this->handle;
		}
		else
			// the handle is still pending -- return it
			return $handle;
	}
}