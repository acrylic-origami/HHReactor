<?hh // strict
namespace HHReactor\Test\ProducerCore;

use HHReactor\Test\ConstructableProducer;
use HHReactor\Test\OperatorTestUtil;

use HackPack\HackUnit\Contract\Assert;
class AppenderTest {
	private static async function empty_iterator(): AsyncIterator<mixed> {
		if(false)
			yield null;
	}
	
	<<Test>>
	public async function test_core_and_appended_empty(Assert $assert): Awaitable<void> {
		$factory = async ($appender) ==> {
			$appender(self::empty_iterator());
			if(false)
				yield null;
		};
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			new ConstructableProducer(Vector{ $factory }),
			[]
		);
	}
	
	<<Test>>
	public async function test_core_nonempty_appended_empty(Assert $assert): Awaitable<void> {
		$factory = async ($appender) ==> {
			$appender(self::empty_iterator());
			await \HH\Asio\later();
			yield 42;
		};
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			new ConstructableProducer(Vector{ $factory }),
			[ 42 ]
		);
	}
	
	<<Test>>
	public async function test_core_empty_appended_nonempty(Assert $assert): Awaitable<void> {
		$factory = async ($appender) ==> {
			$appender(async {
				await \HH\Asio\later();
				yield 42;
			});
			if(false)
				yield null;
		};
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			new ConstructableProducer(Vector{ $factory }),
			[ 42 ]
		);
	}
	
	<<Test>>
	public async function test_core_and_appended_nonempty(Assert $assert): Awaitable<void> {
		$factory = async ($appender) ==> {
			$appender(async {
				await \HH\Asio\later();
				yield 'inner';
			});
			await \HH\Asio\later();
			yield 'outer';
		};
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			new ConstructableProducer(Vector{ $factory }),
			[ 'inner', 'outer' ]
		);
	}
	
	<<Test>>
	public async function test_nested_append(Assert $assert): Awaitable<void> {
		$factory = async ($appender) ==> {
			$appender(async {
				$appender(async {
					yield 'inner inner';
				});
				if(false)
					yield null;
			});
			if(false)
				yield null;
		};
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			new ConstructableProducer(Vector{ $factory }),
			[ 'inner inner' ]
		);
	}
}