<?hh // strict
namespace HHRx;
use HHRx\Collection\Producer;
use HHRx\Asio\ConditionWaitHandleWrapper;
class AsyncPoll {
	public static async function awaitable<Tk, T>(KeyedIterable<Tk, Awaitable<T>> $subawaitables): AsyncKeyedIterator<Tk, T> {
		$race_handle = new ConditionWaitHandleWrapper(); // ?Wrapper<ConditionWaitHandle>
		$pending_subawaitables = 
			$subawaitables->filterWithKey((Tk $k, Awaitable<T> $v) ==> !$v->getWaitHandle()->isFinished())
			              ->mapWithKey(async (Tk $k, Awaitable<T> $v) ==> {
			              		try {
				              		$resolved_v = await $v;
				              		await $race_handle->succeed(tuple($k, $resolved_v));
				              	}
				              	catch(\Exception $e) {
				              		await $race_handle->fail($e);
				              	}
			              	});
		if(!is_null($pending_subawaitables->firstValue())) {
			$total_awaitable = async {
				await \HH\Asio\m($pending_subawaitables);
			};
			$race_handle->set($total_awaitable->getWaitHandle());
			while(!$total_awaitable->getWaitHandle()->isFinished()) {
				list($k, $v) = await $race_handle;
				$race_handle->reset();
				yield $k => $v;
				await \HH\Asio\later();
			}
			try {
				$race_handle->getWaitHandle()->result();
				// See similar clause in self::producer() for explanation
			}
			catch(\InvalidArgumentException $e) {}
		}
		else
			foreach($subawaitables as $k => $v)
				yield $k => $v->getWaitHandle()->result();
	}
	private static function fn(): void {}
	public static async function producer<T>(Iterable<Producer<T>> $producers): AsyncIterator<T> {
		$race_handle = new ConditionWaitHandleWrapper();
		$pending_producers = Vector{};
		$total_awaitable = null;
		foreach($producers as $producer) {
			foreach($producer->fast_forward() as $v)
				yield $v; // this is not a trivial procedure: what if this Iterator is instantiated outside of a `HH\Asio\join`, `next`ed, then control is handed back to the join? 
			if(!$producer->isFinished()) {
				// vital that they aren't finished, so that these notifiers won't try to notify the race_handle before we get a chance to `set` it just afterwards
				$pending_producers->add(async {
					try {
						foreach($producer await as $v) {
							await $race_handle->succeed($v);
						}
					}
					catch(\Exception $e) {
						await $race_handle->fail($e);
					}
				});
				$total_awaitable = async {
					await \HH\Asio\v($pending_producers);
				};
				$race_handle->set($total_awaitable->getWaitHandle());
			}
		}
		while(!is_null($total_awaitable) && !$total_awaitable->getWaitHandle()->isFinished()) {
			invariant(!is_null($race_handle), 'Since this is running, there must be at least one pending producer, so $race_handle can\'t have finished yet.');
			$v = await $race_handle;
			$race_handle->reset();
			yield $v;
			await \HH\Asio\later(); // although the `$total_awaitable` completes here, since the `ConditionWaitHandle` isn't `await`ed, the error doesn't propagate.
		}
		if(!is_null($race_handle)) { // then it must be finished by this point
			// screen for exceptions aside outside of the expected one from the final element ('ConditionWaitHandle not notified by its child')
			try {
				$race_handle->getWaitHandle()->result();
			}
			catch(\InvalidArgumentException $e) {
				// The last element necessarily triggers this exception during this `later` await, because this `later` pushes this scope behind the last producer's `later` (from the corresponding `_notify` call) in the scheduler, expiring `total_awaitable` and raising `InvalidArgument Exception`. 
				// We purposefully ignore this Exception, because there's no practical way to avoid resetting it on the final arc of the iteration.
			}
		}
	}
}