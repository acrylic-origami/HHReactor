<?hh // strict
namespace HHRx\Collection;
class AsyncIteratorWrapper<+T> implements AsyncIterator<T>, IHaltable {
	private ?Haltable<?(mixed, T)> $handle = null;
	public function __construct(private AsyncIterator<T> $iterator) {} // Note: cold behaviour
	public function next(): Awaitable<?(mixed, T)> {
		$handle = $this->handle;
		if(is_null($handle) || $handle->getWaitHandle()->isFinished()) {
			// refresh the handle if this is the first `next` call or the underlying awaitable has resolved
			$this->handle = new Haltable($this->iterator->next());
			return $this->handle;
		}
		else
			// the handle is still pending -- return it
			return $handle;
	}
	public async function halt(?\Exception $e = null): Awaitable<void> {
		$handle = $this->handle;
		invariant(!is_null($handle), 'Attempted to halt producer before starting iteration.');
		await $handle->halt($e);
	}
}