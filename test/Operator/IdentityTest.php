<?hh // strict
namespace HHReactor\Test\Operator;

use HHReactor\Producer;

use HHReactor\Test\TestException;
use HHReactor\Test\OperatorTestUtil;

use HackPack\HackUnit\Contract\Assert;
class IdentityTest {
	private static async function empty_iterator(): AsyncIterator<mixed> {
		if(false)
			yield null;
	}
	
	<<Test>>
	public async function test_ready_values(Assert $assert): Awaitable<void> {
		$source = [ 0, 1, 'foo' ];
		$iterator = async {
			foreach($source as $v) {
				yield $v;
			}
		};
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::create($iterator),
			$source
		);
	}
	
	<<Test>>
	public async function test_all_awaited_values(Assert $assert): Awaitable<void> {
		$source = [ 0, 1, 'foo' ];
		$iterator = async {
			foreach($source as $v) {
				await \HH\Asio\later();
				yield $v;
				await \HH\Asio\later();
			}
		};
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::create($iterator),
			$source
		);
	}
	
	<<Test>>
	public async function test_empty(Assert $assert): Awaitable<void> {
		$empty = self::empty_iterator();
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::create($empty),
			[]
		);
	}
	
	<<Test>>
	public async function test_exceptions(Assert $assert): Awaitable<void> {
		await OperatorTestUtil::exception_asserts(
			$assert,
			($producer) ==> $producer
		);
	}
}