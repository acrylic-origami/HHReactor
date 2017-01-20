<?hh // strict
namespace HHRx\Collection;
class Haltable<+T> implements Awaitable<?T>, IHaltable {
	private ConditionWaitHandle<?T> $handle;
	private Awaitable<Vector<?T>> $vec;
	public function __construct(private Awaitable<T> $awaitable) {
		// Two workarounds:
		// One for ConditionWaitHandle not accepting Awaitable<mixed>
		$void_awaitable = async {
			await \HH\Asio\later();
			await $this->awaitable;
		};
		// One for no \HH\Asio\va yet
		$null_awaitable = async {
			await $void_awaitable;
			return null;
		};
		$this->handle = ConditionWaitHandle::create($void_awaitable->getWaitHandle());
		$this->vec = \HH\Asio\v(Vector{
			$null_awaitable,
			async {
				return await $this->handle;
			}, async {
				$v = await $this->awaitable;
				if(!$this->handle->isFinished())
					// else we were beat to the punch by a `halt` call
					$this->handle->succeed($v);
				return null; // currently a limitation of \Asio\v, waiting for Asio\va (variadic)
			}
		});
	}
	public function getWaitHandle(): WaitHandle<?T> {
		$T_awaitable = async {
			$T = await $this->vec;
			return $T[1];
		};
		return $T_awaitable->getWaitHandle();
	}
	public async function halt(?\Exception $e = null): Awaitable<void> {
		$this->soft_halt($e);
		await \HH\Asio\later();
	}
	public function soft_halt(?\Exception $e = null): void {
		if(!is_null($e))
			$this->handle->fail($e);
		else
			$this->handle->succeed(null);
	}
}