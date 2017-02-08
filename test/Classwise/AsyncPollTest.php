<?hh // strict
namespace HHReactor\Test\Classwise;
use HHReactor\AsyncPoll;
use HHReactor\Test\CustomException;
use HHReactor\Collection\Producer;
use PHPUnit\Framework\TestCase;
class AsyncPollTest extends TestCase {
	public static async function n_later(int $n): Awaitable<int> {
		for($i = 0; $i < $n; $i++)
			await \HH\Asio\later();
		return $n;
	}
	private static function make_interspersed_producers(int $num_producers = 3, int $num_iterations = 3): Vector<Producer<int>> {
		return (new Vector(range(1, $num_producers)))->map((int $n) ==> new Producer(async {
			await self::n_later($n);
			for($i = 0; $i < $num_iterations; $i++) {
				yield $i * $num_producers + $n;
				await self::n_later($num_producers);
			}
		}));
	}
	private static async function collect<T>(AsyncIterator<T> $producer): Awaitable<Vector<T>> {
		$ret = Vector{};
		foreach($producer await as $v)
			$ret->add($v);
		return $ret;
	}
	public function test_awaitable_simple(): void {
		$wait_handles = (Vector{3, 4, 1, 2})->map(class_meth(self::class, 'n_later'));
		$vec = \HH\Asio\join(self::collect(AsyncPoll::awaitable($wait_handles)));
		$this->assertEquals(Vector{1, 2, 3, 4}, $vec);
	}
	public function test_producer_emit_end(): void {
		$producer = new Producer(async {
			await \HH\Asio\later();
			yield 1;
		});
		$vec = \HH\Asio\join(async {
			$ret = Vector{};
			foreach(AsyncPoll::producer(Vector{ $producer }) await as $v)
				$ret->add($v);
			return $ret;
		});
		$this->assertEquals(Vector{ 1 }, $vec);
	}
	public function test_producer_await_end(): void {
		$producer = new Producer(async {
			await \HH\Asio\later();
			yield 1;
			await \HH\Asio\later();
		});
		$vec = \HH\Asio\join(async {
			$ret = Vector{};
			foreach(AsyncPoll::producer(Vector{ $producer }) await as $v)
				$ret->add($v);
			return $ret;
		});
		$this->assertEquals(Vector{ 1 }, $vec);
	}
	public function test_producer_simple(): void {
		$num_producers = 3;
		$num_iterations = 3;
		$vec = \HH\Asio\join(self::collect(AsyncPoll::producer(self::make_interspersed_producers($num_producers, $num_iterations))));
		$this->assertEquals(new Vector(range(1, 9)), $vec);
	}
	public function test_eager_producers(): void {
		$producers = array_map((int $v) ==> new Producer(async {
			yield $v;
			yield $v + 1;
		}), range(1, 19, 2));
		$vec = \HH\Asio\join(self::collect(AsyncPoll::producer($producers)));
		$arr = $vec->toArray();
		sort($arr); // canonicalize
		$this->assertEquals(range(1, 20), $arr);
	}
	public function test_producer_light_backpressure(): void {
		$multicast_handle = \HH\Asio\later();
		$producers = array_map((int $v) ==> new Producer(async {
			await $multicast_handle;
			yield $v;
		}), range(1, 10)); // when the multicast handle resolves, all producers are eagerly-executed to completion
		$vec = \HH\Asio\join(self::collect(AsyncPoll::producer($producers)));
		$arr = $vec->toArray();
		sort($arr); // canonicalize
		$this->assertEquals(range(1, 10), $arr);
	}
	public function test_producer_nested(): void {
		$core = new Producer(async {
			await \HH\Asio\later();
			yield 1;
		});
		$merge = new Producer(AsyncPoll::producer(Vector{ $core, clone $core }));
		$vec = \HH\Asio\join(self::collect(AsyncPoll::producer(Vector{ $merge, clone $core })));
		$this->assertEquals(Vector{ 1, 1, 1 }, $vec);
	}
	
	/**
	 * @expectedException        HHReactor\Test\CustomException
	 * @expectedExceptionMessage 1
	*/
	public function test_awaitable_exception(): void {
		// $this->expectException(CustomException::class);
		$awaitables = (Vector{1, 1, 3, 2, 2})->map(async (int $n) ==> {
			await self::n_later($n);
			throw new CustomException((string) $n);
		});
		\HH\Asio\join(self::collect(AsyncPoll::awaitable($awaitables)));
	}
	
	/**
	 * @expectedException        HHReactor\Test\CustomException
	 * @expectedExceptionMessage 1
	*/
	public function test_producer_exception(): void {
		// $this->expectException(CustomException::class);
		$awaitables = (Vector{1, 1, 3, 2, 2})->map((int $n) ==> new Producer(async {
			await self::n_later($n);
			yield null;
			throw new CustomException((string) $n);
		}));
		\HH\Asio\join(self::collect(AsyncPoll::producer($awaitables)));
	}
}