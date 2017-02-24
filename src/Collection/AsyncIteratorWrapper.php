<?hh // strict
namespace HHReactor\Collection;
use HHReactor\Asio\Haltable;
use HHReactor\Asio\HaltResult;
class AsyncIteratorWrapper<+T> implements AsyncIterator<HaltResult<T>>, IHaltable {
	private ?Haltable<?(mixed, T)> $handle = null;
	public function __construct(private AsyncIterator<T> $iterator) {} // Note: cold behaviour
	public async function next(): Awaitable<?(mixed, HaltResult<T>)> {
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
					return null;
			}
			$handle = new Haltable($pending_next);
			$this->handle = $handle;
		}
		$next = await $handle;
		$result = $next['result'] ?? tuple(null, null);
		return tuple($result[0], shape('_halted' => $next['_halted'], 'result' => $result[1]));
	}
	public async function halt(?\Exception $e = null): Awaitable<void> {
		$handle = $this->handle;
		invariant(!is_null($handle), 'Attempted to halt producer before starting iteration.');
		await $handle->halt($e);
	}
}