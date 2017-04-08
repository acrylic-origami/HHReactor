<?hh // strict
namespace HHReactor\Asio;
use HHReactor\Asio\Haltable;
use HHReactor\Collection\Producer;
/**
 * Allow T-typed `Awaitable`s to be added from any scope at any time, and eventually resolve to a time-ordered Vector of their results.
 */
class Extendable<T> implements Dependent<T>, Awaitable<\ConstMap<string, T>> {
	private Haltable<Vector<T>> $total_awaitable;
	/**
	 * @var - `await`ed and recycled to push new Awaitables onto the scheduler.
	 */
	private Haltable<mixed> $partial;
	private Dependencies<T> $dependencies;
	public function __construct(Awaitable<T> $dependency) {
		$this->dependencies = new Dependencies();
		
		$this->partial = new Haltable($this->dependencies->depend($dependency));
		/* HH_IGNORE_ERROR[4110] $v['result'] is always type T (which may or may not be nullable) because !_halted */
		$this->total_awaitable = new Haltable(async {
			do {
				invariant(!$this->partial->getWaitHandle()->isFinished() || !$this->partial->getWaitHandle()->result()['_halted'], 'Implementation error: `partial` halted but not replaced. Aborting to prevent infinite loop.');
				$v = await $this->partial;
			}
			while($v['_halted']);
			// Assuming $this->partial is updated with \HH\Asio\v; this should be done at this point.
			return \HH\Asio\v($this->dependencies->get_dependencies())->getWaitHandle()->result();
		});
	}
	
	public function soft_halt(?\Exception $e = null): void {
		$this->total_awaitable->soft_halt($e);
	}
	
	public function is_halted(): bool {
		return $this->total_awaitable->is_halted();
	}
	
	public function getWaitHandle(): WaitHandle<\ConstMap<string, T>> {
		return $this->_getWaitHandle()->getWaitHandle();
	}
	
	public function get_dependencies(): ConstDependencies<T> {
		return $this->dependencies;
	}
	
	<<__Memoize>>
	private async function _getWaitHandle(): Awaitable<\ConstMap<string, T>> {
		$halt_result = await $this->total_awaitable;
		if($halt_result['_halted']) {
			// retrieve completed items
			return $this->dependencies->get_dependencies()
			                          ->filter(($subawaitable) ==> $subawaitable->getWaitHandle()->isFinished())
			                          ->map(($subawaitable) ==> $subawaitable->getWaitHandle()->result());
		}
		else {
			/* HH_IGNORE_ERROR[4110] $halt_result['result'] is always type T (which may or may not be nullable) because !_halted */
			return $halt_result['result'];
		}
	}
	
	/**
	 * Add the result of the incoming `Awaitable` to the list this Extendable resolves to when the incoming `Awaitable` resolves.
	 * 
	 * **Timing**:
	 * - **Spec**:
	 *   - Consider values from members of `$subawaitables` that resolve after the value of `$incoming` resolves. The former may still precede the latter in the final `Vector`, depending on the ready queue during and after this arc.
	 *   - [UNENFORCEABLE] Two `Awaitable`s with strict order that are unfinished when extending this `Extendable` must preserve their order in the final `Vector`.
	 *     - If both are finished at the time of calling `soft_extend`, their order in the final `Vector` must be the call order, regardless of the original strict ordering.
	 *   - Must not cause a cyclic dependency when `await`ing `$this`.
	 */
	public function extend(Awaitable<T> $incoming): void {
		if($this->total_awaitable->getWaitHandle()->isFinished())
			throw new \RuntimeException('Attempted to extend a finished Extendable.');
		
		if(!$this->partial->getWaitHandle()->isFinished())
			// sketchy but possible: partial is provably finished before total_awaitable and the calling scope wants to extend with something else
			$this->partial->soft_halt();
		
		/* HH_IGNORE_ERROR[4015] `depend` is an identity function with a side effect */
		$this->dependencies->depend($incoming);
		$this->partial = new Haltable(\HH\Asio\v($this->dependencies->get_dependencies()));
	}
}