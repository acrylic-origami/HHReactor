<?hh // strict
namespace HHReactor\Test\Operator;

use HHReactor\Producer;

use HHReactor\Test\TestException;
use HHReactor\Test\OperatorTestUtil;

use HackPack\HackUnit\Contract\Assert;

class GroupByTest {
	
	<<Test>>
	public async function test_unique_identity_keys(Assert $assert): Awaitable<void> {
		$iterator = async {
			for($i = 0; $i < 4; $i++)
				yield $i;
		};
		$producer = Producer::create($iterator)
		                    ->group_by(($I) ==> $I);
		await OperatorTestUtil::nested_hot_cold_value_assert(
			$assert,
			$producer,
			[[ 0 ], [ 1 ], [ 2 ], [ 3 ]]
		);
	}
	
	<<Test>>
	public async function test_sparse_keys(Assert $assert): Awaitable<void> {
		$iterator = async {
			for($i = 0; $i < 10; $i++)
				yield $i;
		};
		$producer = Producer::create($iterator)
		                    ->group_by(($i) ==> $i % 2);
		await OperatorTestUtil::nested_hot_cold_value_assert(
			$assert,
			$producer,
			[range(0, 8, 2), range(1, 9, 2)]
		);
	}
	
	<<Test>>
	public async function test_exceptions(Assert $assert): Awaitable<void> {
		await OperatorTestUtil::exception_asserts(
			$assert,
			($producer) ==>
				$producer->group_by(
					($_) ==> 'arbitrary_key'
				)
		);
	}
}