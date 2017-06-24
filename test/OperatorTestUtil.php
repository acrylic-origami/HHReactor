<?hh // strict
namespace HHReactor\Test;
use HHReactor\Producer;
use HackPack\HackUnit\Contract\Assert;
class OperatorTestUtil {
	public static function orderless_sequence_comparator<T>(Traversable<T> $left, Traversable<T> $right): bool {
		$left_array = is_array($left) ? $left : iterator_to_array($left);
		$right_array = is_array($right) ? $right : iterator_to_array($right);
		
		
		// canonicalize
		sort($left_array);
		sort($right_array);
		
		foreach($left_array as $k => $v)
			if($right_array[$k] !== $v)
				return false;
		
		return true;
	}
	
	public static async function direct_collapse<T>(Producer<T> $producer): Awaitable<\ConstVector<T>> {
		$ret = Vector{};
		foreach($producer await as $v)
			$ret->add($v);
		return $ret;
	}
	public static async function nested_collapse<T>(Producer<Producer<T>> $nested_producer): Awaitable<\ConstVector<\ConstVector<T>>> {
		$ret = Vector{};
		foreach($nested_producer await as $producer) {
			$collapsed = await self::direct_collapse(clone $producer);
			$ret->add($collapsed);
		}
		return $ret;
	}
	private static function hot_cold<T>(Producer<T> $producer): Awaitable<\ConstVector<\HH\Asio\ResultOrExceptionWrapper<\ConstVector<T>>>> {
		$clone = clone $producer;
		return \HH\Asio\vw(Vector{
			self::direct_collapse($producer),
			self::direct_collapse($producer),
			self::direct_collapse($clone)
		});
	}
	private static function nested_hot_cold<T>(Producer<Producer<T>> $nested_producer): Awaitable<\ConstVector<\HH\Asio\ResultOrExceptionWrapper<\ConstVector<\ConstVector<T>>>>> {
		$clone = clone $nested_producer;
		return \HH\Asio\vw(Vector{
			self::nested_collapse($nested_producer),
			self::nested_collapse($nested_producer),
			self::nested_collapse($clone)
		});
	}
	// (function(Producer<T>): Awaitable<\ConstVector<T>>) $collapser = class_meth(self::class, 'direct_collapse')
	public static async function hot_cold_value_assert<T>(
		Assert $assert,
		Producer<T> $producer,
		Container<T> $expected): Awaitable<void> {
		list($hot_a, $hot_b, $cold) = await self::hot_cold($producer);
		$total_hot = $hot_a->getResult()->concat($hot_b->getResult());
		
		$assert->container($cold->getResult())->containsOnly($expected); // 'Cold iteration produces expected?'
		$assert->container($total_hot)->containsOnly($expected);
	}
	public static async function nested_hot_cold_value_assert<T>(
		Assert $assert,
		Producer<Producer<T>> $producer,
		Container<Container<T>> $expected): Awaitable<void> {
		list($hot_a, $hot_b, $cold) = await self::nested_hot_cold($producer);
		$total_hot = $hot_a->getResult()->concat($hot_b->getResult());
		// var_dump($total_hot);
		$assert->container($cold->getResult())->containsOnly($expected, class_meth(self::class, 'orderless_sequence_comparator')); // 'Cold iteration produces expected?'
		$assert->container($total_hot)->containsOnly($expected, class_meth(self::class, 'orderless_sequence_comparator'));
	}
	public static async function hot_cold_exception_assert(
		Assert $assert,
		Producer<mixed> $producer,
		\Exception $expected): Awaitable<void> {
		list($hot_a, $hot_b, $cold) = await self::hot_cold($producer);
		
		// exactly one of the hot producers must produce the exception
		$assert->bool(!$hot_a->isFailed())
		       ->is($hot_b->isFailed());
		$assert->whenCalled(() ==> {
			$hot_a->getResult(); // @throws Exception (hopefully)
			$hot_b->getResult(); // @throws Exception (hopefully)
		})
		       ->willThrowClassWithMessage(get_class($expected), $expected->getMessage());
		
		// the cold producer must always produce the exception
		$assert->whenCalled(() ==> {
			throw $cold->getException();
		})
		       ->willThrowClassWithMessage(get_class($expected), $expected->getMessage());
	}
	
	public static async function exception_asserts(
		Assert $assert,
		(function(Producer<mixed>): Producer<mixed>) $operator): Awaitable<void> {
		$exception = new TestException('bar');
		$alone = async {
			throw $exception;
			if(false)
				yield 'foo'; // needed to make this async block into an AsyncGenerator
		};
		$awaited_alone = async {
			await \HH\Asio\later();
			throw $exception;
			if(false)
				yield 'foo'; // needed to make this async block into an AsyncGenerator
		};
		$pre = async {
			throw $exception;
			yield 'foo';
		};
		
		// yield some elements.
		//  (note the element isn't guaranteed to come before the exception
		//   hence why we're only testing that the exception is eventaully
		//   thrown)
		$post = async {
			yield 'foo';
			throw $exception;
		};
		$awaited_ready_post = async {
			await \HH\Asio\later();
			yield 'foo';
			throw $exception;
		};
		$ready_awaited_post = async {
			yield 'foo';
			await \HH\Asio\later();
			throw $exception;
		};
		$situations = [ $alone , $awaited_alone, $pre, $post, $awaited_ready_post, $ready_awaited_post ];
		foreach($situations as $situation)
			await self::hot_cold_exception_assert($assert, Producer::create($situation), $exception);
	}
}