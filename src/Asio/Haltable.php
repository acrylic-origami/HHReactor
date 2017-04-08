<?hh // strict
namespace HHReactor\Asio;
use \HHReactor\Wrapper;
/**
 * Emit value unless interrupted
 */
class Haltable<+T> implements Dependent<mixed>, Awaitable<HaltResult<T>> {
	// private ConditionWaitHandle<HaltResult<T>> $handle;
	/**
	 * Set up notifier of default value. Check if provided `Awaitable` is ready-wait.
	 * 
	 * **Timing**
	 * - **Spec**
	 *   - If `$awaitable` is ready-wait, then this Haltable must also be ready-wait.
	 * @param $awaitable - Resolve to default value this Haltable will mirror if not halted.
	 */
	private ConditionWaitHandle<HaltResult<T>> $handle;
	private Dependencies<mixed> $dependencies;
	public function __construct(Awaitable<T> $dependency) {
		$this->dependencies = new Dependencies();
		$this->handle = ConditionWaitHandle::create(\HH\Asio\later()->getWaitHandle());
		$notifier = async {
			try {
				$v = await $this->dependencies->depend($dependency);
				$handle = $this->handle;
				if(!$handle->isFinished())
					// if we weren't beat to the punch by a `halt` call
					$handle->succeed(shape('_halted' => false, 'result' => $v));
			}
			catch(\Exception $e) {
				$handle = $this->handle;
				if(!$handle->isFinished())
					$handle->fail($e); // emit the exception if anyone's listening
			}
		};
		if(!$notifier->getWaitHandle()->isFinished())
			$this->handle = ConditionWaitHandle::create($notifier->getWaitHandle());
	}
	
	// <<__Deprecated('Misleading behavior: when used with the intention of resetting, there is no guarantee the reset operation will happen immediately after halting, despite immediately returning control.')>>
	// public async function halt(?\Exception $e = null): Awaitable<void> {
	// 	// to be used in async code, where idempotency is not expected, and instant propagation is
	// 	$this->soft_halt($e);
	// 	await \HH\Asio\later();
	// }
	
	public function getWaitHandle(): WaitHandle<HaltResult<T>> {
		return $this->handle;
	}
	
	public function get_dependencies(): ConstDependencies<mixed> {
		return $this->dependencies;
	}
	
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
		$original_handle = $this->handle;
		// try as hard as you can to stop the work by decrementing the refcount to the WaitHandle
		$this->handle = ConditionWaitHandle::create(\HH\Asio\later()->getWaitHandle());
		
		// the below is only valid for ExternalThreadEventWaitHandle and SleepWaitHandle as of HHVM 3.16. Not terribly useful for the mainly user-land stuff we're doing here
		// if(!is_null($e)) {
		// 	// `try` even harder to stop the work
		// 	try {
		// 		\HH\Asio\cancel($this->handle, $e);
		// 	}
		// 	catch(\InvalidArgumentException $e) {}
		// }
		
		if(!is_null($e)) {
			$original_handle->fail($e);
			$this->handle->fail($e);
		}
		else {
			$original_handle->succeed(shape('_halted' => true, 'result' => null));
			$this->handle->succeed(shape('_halted' => true, 'result' => null));
		}
	}
}