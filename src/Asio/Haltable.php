<?hh // strict
namespace HHReactor\Asio;
use \HHReactor\Wrapper;
use \HH\Asio\AsyncCondition;
/**
 * Emit value unless interrupted
 */
class Haltable<+T> implements Awaitable<HaltResult<T>> {
	// private ConditionWaitHandle<HaltResult<T>> $handle;
	/**
	 * Set up notifier of default value. Check if provided `Awaitable` is ready-wait.
	 * 
	 * **Timing**
	 * - **Spec**
	 *   - If `$awaitable` is ready-wait, then this Haltable must also be ready-wait.
	 * @param $awaitable - Resolve to default value this Haltable will mirror if not halted.
	 */
	private WaitHandle<HaltResult<T>> $handle;
	// private Dependencies<mixed> $dependencies;
	public function __construct((function((function(?\Exception): void)): Awaitable<T>) $dependency) {
		// $this->dependencies = new Dependencies();
		$this->handle = AsyncCondition::create((AsyncCondition<HaltResult<T>> $condition) ==> self::_generate_notifier($dependency, $condition))
		                              ->getWaitHandle();
	}
	
	private static async function _generate_notifier((function((function(?\Exception): void)): Awaitable<T>) $dependency, AsyncCondition<HaltResult<T>> $condition): Awaitable<void> {
		try {
			$v = await $dependency((?\Exception $e) ==> self::_halt($e, $condition));
			if(!$condition->isNotified())
				// if we weren't beat to the punch by a `halt` call
				$condition->succeed(shape('_halted' => false, 'result' => $v));
		}
		catch(\Exception $e) {
			if(!$condition->isNotified())
				$condition->fail($e); // emit the exception if anyone's listening
		}
	}
	
	private static function _halt(?\Exception $e, AsyncCondition<HaltResult<T>> $condition): void {
		if(!is_null($e))
			$condition->fail($e);
		else
			$condition->succeed(shape('_halted' => true, 'result' => null));
	}
	
	// <<__Deprecated('Misleading behavior: when used with the intention of resetting, there is no guarantee the reset operation will happen immediately after halting, despite immediately returning control.')>>
	// public async function halt(?\Exception $e = null): Awaitable<void> {
	// 	// to be used in async code, where idempotency is not expected, and instant propagation is
	// 	$this->soft_halt($e);
	// 	await \HH\Asio\later();
	// }
	
	public function getWaitHandle(): WaitHandle<HaltResult<T>> {
		return $this->handle->getWaitHandle();
	}
	
	// public function get_dependencies(): ConstDependencies<mixed> {
	// 	return $this->dependencies;
	// }
	
	/**
	 * Check if the Haltable was halted to a finish.
	 */
	public function is_halted(): bool {
		return $this->handle->isFinished() && $this->handle->result()['_halted'];
	}
}