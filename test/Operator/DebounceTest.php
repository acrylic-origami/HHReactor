<?hh // strict
namespace HHReactor\Test\Operator;

use HHReactor\Producer;

use HHReactor\Test\TestException;
use HHReactor\Test\OperatorTestUtil;

use HackPack\HackUnit\Contract\Assert;

class DebounceTest {
	// refusing to test Producer::debounce(0) due to undefined behavior of \HH\Asio\usleep(0) + race conditions
	
	<<Test>>
	public async function test_ready_items_only_emits_last(Assert $assert): Awaitable<void> {
		$iterator = async {
			for($i = 0; $i < 3; $i++) {
				await \HH\Asio\later();
				yield $i;
			}
		};
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::create($iterator)
			        ->debounce((int)1E6),
			[2]
		);
	}
	
	<<Test>>
	// Caution: although unlikely, could fail from race condition
	public async function test_emit_some(Assert $assert): Awaitable<void> {
		$iterator = async {
			yield 1;
			yield 2;
			await \HH\Asio\later();
			yield 3;
			await \HH\Asio\usleep((int)100E3);
			yield 4;
			await \HH\Asio\usleep((int)100E3);
			yield 5;
			await \HH\Asio\usleep((int)100E3);
			yield 6;
			await \HH\Asio\usleep((int)200E3);
			yield 7;
		};
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::create($iterator)
			        ->debounce((int)150E3),
			[6, 7]
		);
	}
	
	<<Test>>
	public async function test_exceptions(Assert $assert): Awaitable<void> {
		await OperatorTestUtil::exception_asserts(
			$assert,
			($producer) ==>
				$producer->debounce(1)
		);
	}
}