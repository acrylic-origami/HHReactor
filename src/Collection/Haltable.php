<?hh // strict
namespace HHReactor\Collection;
class Haltable<+T> implements Awaitable<?T>, IHaltable {
	private ConditionWaitHandle<?T> $handle;
	public function __construct(private Awaitable<T> $awaitable) {
		$this->handle = ConditionWaitHandle::create(\HH\Asio\later()->getWaitHandle()); // dummy Awaitable if $awaitable is already finished
		$notifier = async {
			try {
				$v = await $this->awaitable;
				$handle = $this->handle;
				if(!$handle->isFinished())
					// if we weren't beat to the punch by a `halt` call
					$handle->succeed($v);
			}
			catch(\Exception $e) {
				$this->handle->fail($e);
			}
		};
		if(!$awaitable->getWaitHandle()->isFinished()) {
			$this->handle = ConditionWaitHandle::create($notifier->getWaitHandle());
		}
	}
	private function fn(): void {}
	public function getWaitHandle(): WaitHandle<?T> {
		$T_awaitable = async {
			// try {
				return await $this->handle;
			// }
			// catch(\Exception $e) {
			// 	echo 'CAUGHT!';
			// 	// /* HH_IGNORE_ERROR[4105] */
			// 	// debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			// 	var_dump($e);
			// 	throw $e;
			// 	// return null;
			// }
		};
		return $T_awaitable->getWaitHandle();
	}
	public async function halt(?\Exception $e = null): Awaitable<void> {
		// to be used in async code, where idempotency is not expected, and instant propagation is
		if(!is_null($e))
			$this->handle->fail($e);
		else
			$this->handle->succeed(null);
		await \HH\Asio\later();
	}
	public function soft_halt(?\Exception $e = null): void {
		// to be used in synchronous code, where idempotent behaviour is expected
		if(!$this->handle->isFinished())
			if(!is_null($e))
				$this->handle->fail($e);
			else
				$this->handle->succeed(null);
	}
}