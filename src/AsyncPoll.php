<?hh // strict
namespace HHReactor;
use HHReactor\Collection\Producer;
use HHReactor\Asio\ResettableConditionWaitHandle;
class AsyncPoll {
	public static async function awaitable<T>(Iterable<Awaitable<T>> $subawaitables): AsyncIterator<T> {
		$race_handle = new ResettableConditionWaitHandle(); // ?Wrapper<ConditionWaitHandle>
		$pending_subawaitables = 
			$subawaitables->filter((Awaitable<T> $v) ==> !$v->getWaitHandle()->isFinished())
			              ->map(async (Awaitable<T> $v) ==> {
			              		try {
				              		$resolved_v = await $v;
				              		await $race_handle->succeed($resolved_v);
				              	}
				              	catch(\Exception $e) {
				              		await $race_handle->fail($e);
				              	}
			              	});
		if(!is_null($pending_subawaitables->firstValue())) {
			$total_awaitable = async {
				await \HH\Asio\v($pending_subawaitables);
			};
			$race_handle->set($total_awaitable->getWaitHandle());
			while(true) {
				try {
					$v = await $race_handle;
					$race_handle->reset();
					yield $v;
				}
				catch(\InvalidArgumentException $e) {
					if(!$total_awaitable->getWaitHandle()->isFinished() || $total_awaitable->getWaitHandle()->isFailed())
						// Did one of the producers fail, or was the race handle `fail`ed explicitly? If so, rethrow
						throw $e;
					else
						// else, we assume the exception occurs simply because of the logic of the last arc of iteration
						break;
				}
			}
		}
		else
			foreach($subawaitables as $v)
				yield $v->getWaitHandle()->result();
	}
}