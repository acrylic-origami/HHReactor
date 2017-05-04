<?hh // strict
namespace HHReactor\Collection;
use HHReactor\Asio\Haltable;
use HHReactor\Asio\HaltResult;
class AsyncIteratorWrapper<+T> implements AsyncIterator<T> {
	private ?Awaitable<?(mixed, T)> $handle = null;
	public function __construct(private AsyncIterator<T> $iterator) {} // Note: cold behaviour
	public async function next(): Awaitable<?(mixed, T)> {
		if(is_null($this->handle) || \HH\Asio\has_finished($this->handle->getWaitHandle())) {
			// refresh the handle if this is the first `next` call or the underlying awaitable has resolved
			try {
				if(!is_null($this->handle)) {
					$result = \HH\Asio\result($this->handle->getWaitHandle());
					// if(!is_null($result))
					// 	printf("REFRESHED %s\n", $result[1]);
				}
				$this->handle = $this->iterator->next();
			}
			catch(\Exception $e) {
				if($e->getMessage() !== 'Generator is already finished')
					throw $e;
				else
					// for AsyncGenerators, continue issuing iterator termination signal
					return null;
			}
		}
		$next = await $this->handle;
		return $next;
	}
}