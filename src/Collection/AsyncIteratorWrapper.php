<?hh // strict
namespace HHRx\Collection;
class AsyncIteratorWrapper<+T> implements AsyncIterator<T>, IHaltable {
	private ?Haltable<?(mixed, T)> $handle = null;
	public function __construct(private AsyncIterator<T> $iterator) {} // Note: cold behaviour
	public function next(): Awaitable<?(mixed, T)> {
		$handle = $this->handle;
		if(is_null($handle) || $handle->getWaitHandle()->isFinished()) {
			// refresh the handle if this is the first `next` call or the underlying awaitable has resolved
			try {
				$pending_next = $this->iterator->next();
			}
			catch(\Exception $e) {
				if($e->getMessage() !== 'Generator is already finished')
					throw $e;
				else
					// for AsyncGenerators, continue issuing iterator termination signal
					return async { return null; };
			}
			$this->handle = new Haltable($pending_next);
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