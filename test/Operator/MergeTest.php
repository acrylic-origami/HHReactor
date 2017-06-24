<?hh // strict
namespace HHReactor\Test\Operator;

use HHReactor\Producer;

use HHReactor\Test\TestException;
use HHReactor\Test\OperatorTestUtil;

use HackPack\HackUnit\Contract\Assert;

class MergeTest {
	private static async function empty_iterator(): AsyncIterator<mixed> {
		if(false)
			yield null;
	}
	private static async function arbitrary_iterator(): AsyncIterator<mixed> {
		// originally made for exception testing
		yield 42;
	}
	
	<<Test>>
	public async function test_ready_values(Assert $assert): Awaitable<void> {
		$left = async {
			yield 2;
			yield 4;
		};
		$right = async {
			yield 'foo';
			yield 'bar';
		};
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::merge(Vector{
				$left,
				$right
			}),
			[ 'foo', 'bar', 2, 4 ]
		);
	}
	
	<<Test>>
	public async function test_one_awaited_vs_one_ready_values(Assert $assert): Awaitable<void> {
		$left = async {
			yield 2;
			yield 4;
		};
		$right = async {
			await \HH\Asio\later();
			yield 'foo';
			await \HH\Asio\later();
			yield 'bar';
		};
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::merge(Vector{
				$left,
				$right
			}),
			[ 'foo', 'bar', 2, 4 ]
		);
	}
	
	<<Test>>
	public async function test_empty_producers(Assert $assert): Awaitable<void> {
		$iterators = Vector{ self::empty_iterator(), self::empty_iterator() };
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::merge($iterators),
			[]
		);
	}
	
	<<Test>>
	public async function test_many_producers_merging_values(Assert $assert): Awaitable<void> {
		$source = range(0, 100);
		$chunks = new Vector(array_chunk($source, 3));
		$iterators = $chunks->map(async ($values) ==> {
			foreach($values as $v) {
				await \HH\Asio\later();
				yield $v;
			}
		});
		
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::merge($iterators),
			$source
		);
	}
	
	<<Test>>
	public async function test_exceptions(Assert $assert): Awaitable<void> {
		await OperatorTestUtil::exception_asserts(
			$assert,
			($throwing_producer) ==>
				// merge some arbitrary producer with one that throws
				Producer::merge(Vector{
					self::arbitrary_iterator(),
					$throwing_producer
				})
		);
	}
}