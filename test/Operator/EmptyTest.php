<?hh // strict
namespace HHReactor\Test\Operator;

use HHReactor\Producer;

use HHReactor\Test\OperatorTestUtil;

use HackPack\HackUnit\Contract\Assert;

class EmptyTest {
	<<Test>>
	public async function test_that_no_values_are_produced(Assert $assert): Awaitable<void> {
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::empty(),
			[]
		);
	}
}