<?hh // strict
namespace HHReactor\Asio;
class Haltable<+T> implements Awaitable<HaltResult<T>> {
	private ConditionWaitHandle<HaltResult<T>> $handle;
	public function __construct(private Awaitable<T> $awaitable) {
		$this->handle = ConditionWaitHandle::create(\HH\Asio\later()->getWaitHandle());
		$notifier = async {
			try {
				$v = await $this->awaitable;
				$handle = $this->handle;
				if(!$handle->isFinished())
					// if we weren't beat to the punch by a `halt` call
					$handle->succeed(shape('_halted' => false, 'result' => $v));
			}
			catch(\Exception $e) {
				if(!$this->handle->isFinished())
					$this->handle->fail($e); // emit the exception if anyone's listening
			}
		};
		if(!$awaitable->getWaitHandle()->isFinished()) {
			$this->handle = ConditionWaitHandle::create($notifier->getWaitHandle());
		}
	}
	public function getWaitHandle(): WaitHandle<HaltResult<T>> {
		return $this->handle;
	}
	public async function halt(?\Exception $e = null): Awaitable<void> {
		// to be used in async code, where idempotency is not expected, and instant propagation is
		$this->soft_halt($e);
		await \HH\Asio\later();
	}
	public function is_halted(): bool {
		return $this->handle->isFinished() && $this->handle->result()['_halted'];
	}
	
	public function soft_halt(?\Exception $e = null): void {
		// to be used in synchronous code, where idempotent behaviour is expected
		if(!is_null($e))
			$this->handle->fail($e);
		else
			$this->handle->succeed(shape('_halted' => true, 'result' => null));
	}
}