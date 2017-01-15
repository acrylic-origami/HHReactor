<?hh // strict
namespace HHRx;
use HHRx\Collection\Wrapper;
use HHRx\Collection\KeyedProducer;
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
		}
		else
			foreach($subawaitables as $k => $v)
				yield $k => $v->getWaitHandle()->result();
	}
	private static function fn(): void {}
	public static async function producer<Tk, T>(Iterable<KeyedProducer<Tk, T>> $producers): AsyncKeyedIterator<Tk, T> {
		$race_handle = new ConditionWaitHandleWrapper();
		$pending_producers = Vector{};
		foreach($producers as $producer) {
			foreach($producer->fast_forward() as $k => $v)
				yield $k => $v; // this is not a trivial procedure: what if this Iterator is instantiated outside of a `HH\Asio\join`, `next`ed, then control is handed back to the join? 
			if(!$producer->isFinished()) {
				$pending_producers->add(async {
					try {
						foreach($producer await as $k => $v) {
							await $race_handle->succeed(tuple($k, $v));
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
		$total_awaitable = async {
			await \HH\Asio\v($pending_producers);
		};
		// WHY??? Why can't I $total_awaitable->getWaitHandle()->isFinished()
		while(!\HH\Asio\v($pending_producers)->getWaitHandle()->isFinished()) {
			invariant(!is_null($race_handle), 'Since this is running, there must be at least one pending producer, so $race_handle can\'t have finished yet.');
			list($k, $v) = await $race_handle;
			$race_handle->reset();
			yield $k => $v;
			try {
				await \HH\Asio\later();
			}
			catch(\InvalidArgumentException $e) {
				// The last element necessarily triggers this exception during this `later` await, because this `later` pushes this scope behind the last producer's `later` (from the corresponding `_notify` call) in the scheduler, expiring `total_awaitable` and raising `InvalidArgument Exception`. 
			}
		}
	}
}