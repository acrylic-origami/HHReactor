<?hh // strict
namespace HHReactor\Test\Producer;
use HHReactor\Producer;
use HHReactor\Wrapper;

use HHReactor\Test\LifecycleTestProducer;

use HackPack\HackUnit\Contract\Assert;
class LifecycleTest {
	private static async function start_and_detach_producer(AsyncIterator<mixed> $iterator): Awaitable<WaitHandle<void>> {
		$producer = Producer::create(
			$iterator
			// () ==> { echo 'ATTACHED'; },
			// () ==> { echo 'SINGLE DETACH'; },
			// () ==> { echo 'TOTAL DETACH'; }
		);
		// begin running
		await $producer->next();
		
		// totally detach (all instructions are synchronous; probably no race)
		return $producer->get_iterator_edge();
	}
	
	<<Test>>
	public async function test_total_detachment_stops_consumption(Assert $assert): Awaitable<void> {
		$count_up_iterator = async {
			for($i = 0; ; $i++) {
				await \HH\Asio\later();
				yield $i;
			}
		};
		
		$edge = await self::start_and_detach_producer($count_up_iterator);
		await $edge;
		
		// evaluate cleanliness of iterator afterwards
		$sample = Vector{};
		for($i = 0; $i <= 10; $i++) {
			$next = await $count_up_iterator->next();
			/* HH_IGNORE_ERROR[4063] $count_up_iterator is infinite */
			$sample->add($next[1]);
		}
		/* HH_IGNORE_ERROR[4110] Sample must contain 10 elements by this point */
		$assert->container($sample)->containsOnly(range($sample[0], $sample[0] + 10));
	}
	
	// <<Test>>
	// public async function test_child_detach(Assert $assert): Awaitable<void> {
	// 	$iterator = async {
	// 		for($i = 0; ; $i++) {
	// 			await \HH\Asio\later();
	// 			yield $i;
	// 		}
	// 	};
		
	// 	$
	// }
	
	// <<Test>>
	// public async function test_detach_without_total_detach(Assert $assert): Awaitable<void> {
	// 	$iterator = async {
	// 		for($i = 0; ; $i++) {
	// 			await \HH\Asio\later();
	// 			yield $i;
	// 		}
	// 	};
		
	// 	$detached_flag = new Wrapper(false);
	// 	$total_detached_flag = new Wrapper(false);
	// 	$producer = LifecycleTestProducer::_create(
	// 		$iterator,
	// 		() ==> {},
	// 		() ==> $detached_flag->set(true),
	// 		() ==> $total_detached_flag->set(true)
	// 	);
	// 	$assertion_target_clone = clone $producer;
	// 	$parallel_running_clone = clone $producer;
		
	// 	// Assert detachment doesn't happen when unsetting a non-running producer
	// 	$producer = null;
		
	// 	$assert->bool($detached_flag->get())->is(false);
	// 	$assert->bool($total_detached_flag->get())->is(false);
		
	// 	// Assert detachment does happen when unsetting running producers
	// 	$assertions_finished_flag = new Wrapper(false);
		
	// 	await $iterator->next(); // force the clones to be running
	// 	await \HH\Asio\v(Vector{
	// 		async {
	// 			// make another cold consumer
	// 			$clone = clone $producer;
	// 			await $clone->next();
	// 			$clone = null;
	// 			// expect __destruct on $clone to run implicitly here
	// 			$assert->bool($detached_flag->get())->is(true);
	// 			$assert->bool($total_detached_flag->get())->is(false);
	// 			$assertions_finished_flag->set(true);
	// 		},
	// 		async {
	// 			foreach($producer await as $_) {
	// 				if(true === $assertions_finished_flag->get())
	// 					break;
	// 			}
	// 		}
	// 	});
	// }
}