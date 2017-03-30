<?hh // strict
namespace HHReactor\Asio;
/**
 * Emit value unless interrupted
 */
class Haltable<+T> implements Awaitable<HaltResult<T>> {
	private ConditionWaitHandle<HaltResult<T>> $handle;
	/**
	 * Set up notifier of default value. Check if provided `Awaitable` is ready-wait.
	 * 
	 * **Timing**
	 * - **Spec**
	 *   - If `$awaitable` is ready-wait, then this Haltable must also be ready-wait.
	 * @param $awaitable - Resolve to default value this Haltable will mirror if not halted.
	 */
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
	
	// <<__Deprecated('Misleading behavior: when used with the intention of resetting, there is no guarantee the reset operation will happen immediately after halting, despite immediately returning control.')>>
	// public async function halt(?\Exception $e = null): Awaitable<void> {
	// 	// to be used in async code, where idempotency is not expected, and instant propagation is
	// 	$this->soft_halt($e);
	// 	await \HH\Asio\later();
	// }
	
	/**
	 * Check if the Haltable was halted to a finish.
	 */
	public function is_halted(): bool {
		return $this->handle->isFinished() && $this->handle->result()['_halted'];
	}
	
	/**
	 * Halt without immediately returning control to the top level `HH\Asio\join`. Note: one-time, not idempotent.
	 * 
	 * **Timing**:
	 * - **Spec**:
	 *   - This Haltable must be resolved when this call finishes.
	 * @param $e - An exception to propagate if desired.
	 */
	public function soft_halt(?\Exception $e = null): void {
		if(!is_null($e))
			$this->handle->fail($e);
		else
			$this->handle->succeed(shape('_halted' => true, 'result' => null));
	}
}