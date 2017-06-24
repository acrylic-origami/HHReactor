<?hh // strict
namespace HHReactor\Test\Operator;

use HHReactor\Producer;

use HHReactor\Test\TestException;
use HHReactor\Test\OperatorTestUtil;

use HackPack\HackUnit\Contract\Assert;

class WindowTest {
	<<Test>>
	public async function test_dense_windows(Assert $assert): Awaitable<void> {
		$source = range(0, 10);
		$iterator = async {
			foreach($source as $v) {
				await \HH\Asio\later();
				yield $v;
			}
		};
		$dense_signal = async {
			while(true) {
				await \HH\Asio\later();
				yield true;
			}
		};
		$producer = Producer::create($iterator)
		                    ->window(Producer::create($dense_signal))
		                    ->flat_map(($I) ==> $I); // contingent on success of flatmap tests
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			$producer,
			$source
		);
	}
	
	<<Test>>
	public async function test_sparse_windows(Assert $assert): Awaitable<void> {
		$source = range(0, 10);
		$iterator = async {
			foreach($source as $v) {
				await \HH\Asio\later();
				yield $v;
			}
		};
		$sparse_signal = async {
			while(true) {
				await \HH\Asio\later();
				await \HH\Asio\later();
				await \HH\Asio\later();
				await \HH\Asio\later();
				yield true;
			}
		};
		$producer = Producer::create($iterator)
		                    ->window(Producer::create($sparse_signal))
		                    ->flat_map(($I) ==> $I); // contingent on success of flatmap tests
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			$producer,
			$source
		);
	}
	
	<<Test>>
	public async function test_exceptions(Assert $assert): Awaitable<void> {
		$dense_signal = async {
			while(true) {
				await \HH\Asio\later();
				yield true;
			}
		};
		await OperatorTestUtil::exception_asserts(
			$assert,
			($producer) ==> $producer->window(Producer::create($dense_signal))
		);
	}
}