<?hh // strict
namespace HHReactor\Test\Operator;

use HHReactor\Producer;

use HHReactor\Test\TestException;
use HHReactor\Test\OperatorTestUtil;

use HackPack\HackUnit\Contract\Assert;

class FlatMapTest {
	private static async function empty_iterator(): AsyncIterator<mixed> {
		if(false)
			yield null;
	}
	private static async function seequence_to_iterator<T>(Traversable<T> $sequence): AsyncIterator<T> {
		foreach($sequence as $value) {
			await \HH\Asio\later();
			yield $value;
		}
	}
	private static async function nested_sequence_to_producer_iterator<T>(Traversable<Traversable<T>> $nested_sequence): AsyncIterator<Producer<T>> {
		foreach($nested_sequence as $sequence) {
			await \HH\Asio\later();
			yield Producer::create(self::seequence_to_iterator($sequence));
		}
	}
	
	<<Test>>
	public async function test_short_sequences(Assert $assert): Awaitable<void> {
		$nested_sequence = [ [ 1, 2 ], [ 3, 4 ]];
		$producer_iterator = self::nested_sequence_to_producer_iterator($nested_sequence);
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::create($producer_iterator)
			        ->flat_map(($I) ==> $I),
			[ 1, 2, 3, 4 ]
		);
	}
	
	<<Test>>
	public async function test_long_head(Assert $assert): Awaitable<void> {
		$nested_sequence = [ range(1, 100), [ 101, 102 ] ];
		$producer_iterator = self::nested_sequence_to_producer_iterator($nested_sequence);
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::create($producer_iterator)
			        ->flat_map(($I) ==> $I),
			range(1, 102)
		);
	}
	
	<<Test>>
	public async function test_long_tail(Assert $assert): Awaitable<void> {
		$nested_sequence = [ [ 1, 2 ], range(3, 102) ];
		$producer_iterator = self::nested_sequence_to_producer_iterator($nested_sequence);
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::create($producer_iterator)
			        ->flat_map(($I) ==> $I),
			range(1, 102)
		);
	}
	
	<<Test>>
	public async function test_many_short_sequences(Assert $assert): Awaitable<void> {
		$source = range(1, 100);
		$nested_sequence = array_chunk($source, 3);
		$producer_iterator = self::nested_sequence_to_producer_iterator($nested_sequence);
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::create($producer_iterator)
			        ->flat_map(($I) ==> $I),
			range(1, 100)
		);
	}
	
	<<Test>>
	public async function test_many_longer_sequences(Assert $assert): Awaitable<void> {
		$source = range(1, 1000);
		$nested_sequence = array_chunk($source, 33);
		$producer_iterator = self::nested_sequence_to_producer_iterator($nested_sequence);
		await OperatorTestUtil::hot_cold_value_assert(
			$assert,
			Producer::create($producer_iterator)
			        ->flat_map(($I) ==> $I),
			range(1, 1000)
		);
	}
	
	<<Test>>
	public async function test_non_identity_mapper(Assert $assert): Awaitable<void> {
		$source = range(1, 10);
		$nested_sequence = array_chunk($source, 3);
		$iterator = async {
			foreach($nested_sequence as $sequence) {
				yield $sequence;
			}
		};
		$producer = Producer::create($iterator)
		                    ->flat_map(($sequence) ==> Producer::create(self::seequence_to_iterator($sequence)));
	}
	
	<<Test>>
	public async function test_exceptions(Assert $assert): Awaitable<void> {
		await OperatorTestUtil::exception_asserts(
			$assert,
			($producer) ==>
				$producer->flat_map(
					($_) ==> Producer::create(self::empty_iterator())
				)
		);
	}
}