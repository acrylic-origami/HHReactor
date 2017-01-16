<?hh // strict
namespace HHRx\Collection;
class Haltable<+T> implements Awaitable<?T>, IHaltable {
	private ConditionWaitHandle<?T> $handle;
	public function __construct(private Awaitable<?T> $awaitable) {
		$void_awaitable = async {
			await $awaitable;
		};
		$this->handle = ConditionWaitHandle::create($void_awaitable->getWaitHandle());
	}
	public function getWaitHandle(): WaitHandle<?T> {
		$vec = \HH\Asio\v(Vector{
			async {
				await \HH\Asio\later();
				return await $this->handle;
			}, async {
				$v = await $this->awaitable;
				$this->handle->succeed($v);
				return null; // currently a limitation of \Asio\v, waiting for Asio\va (variadic)
			}
		});
		$T_awaitable = async {
			$resolved_vec = await $vec;
			return $resolved_vec[0];
		};
		return $T_awaitable->getWaitHandle();
	}
	public async function halt(?\Exception $e = null): Awaitable<void> {
		if(!is_null($e))
			$this->handle->fail($e);
		else
			$this->handle->succeed(null);
		await \HH\Asio\later();
	}
}