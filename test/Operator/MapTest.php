<?hh // strict
namespace HHReactor\Test\Operator;

use HHReactor\Producer;

use HHReactor\Test\TestException;
use HHReactor\Test\OperatorTestUtil;

use HackPack\HackUnit\Contract\Assert;
class MapTest {
	// public static function identity<T>(T $I): T {
	// 	return $I;
	// }
	
	<<Test>>
	public async function test_identity(Assert $assert): Awaitable<void> {
		$source = [0, 1, 2];
		$iterator = async {
			foreach($source as $i) {
				await \HH\Asio\later();
				yield $i;
			}
		};
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::create($iterator)->map(($I) ==> $I),
			$source
		);
	}
	
	<<Test>>
	public async function test_non_identity(Assert $assert): Awaitable<void> {
		$source = [0, 1, 2];
		$iterator = async {
			foreach($source as $i) {
				await \HH\Asio\later();
				yield $i;
			}
		};
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::create($iterator)->map(($I) ==> pow($I, 2)),
			[0, 1, 4]
		);
	}
	
	<<Test>>
	public async function test_exceptions(Assert $assert): Awaitable<void> {
		await OperatorTestUtil::exception_asserts(
			$assert,
			($producer) ==> $producer->map(($I) ==> $I)
		);
	}
}