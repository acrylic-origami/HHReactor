<?hh // strict
namespace HHReactor\Test\Classwise\Asio;
use HHReactor\Test\CustomException;
use HHReactor\Asio\ResettableConditionWaitHandle;
use PHPUnit\Framework\TestCase;
class ResettableConditionWaitHandleTest extends TestCase {
	private function _void_to_mixed(Awaitable<void> $incoming): Awaitable<mixed> {
		return $incoming;
	}
	private function make_empty_wrapper<T>(): ResettableConditionWaitHandle<T> {
		return new ResettableConditionWaitHandle(\HH\Asio\later()->getWaitHandle());
	}
	private function make_empty_handle<T>(): ConditionWaitHandle<T> {
		return ConditionWaitHandle::create(\HH\Asio\later()->getWaitHandle());
	}
	public function test_no_notify_exception(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('ConditionWaitHandle not notified by its child');
		\HH\Asio\join($this->make_empty_wrapper()->getWaitHandle());
	}
	public function test_succeed_simple(): void {
		$wait_handle = $this->make_empty_wrapper();
		$vec = \HH\Asio\join(\HH\Asio\v(Vector{ $this->_void_to_mixed($wait_handle->succeed(1)), $wait_handle }));
		$this->assertSame(1, $vec[1]);
	}
	public function test_except_fail_simple(): void {
		$this->expectException(CustomException::class);
		
		$wait_handle = $this->make_empty_wrapper();
		$vec = \HH\Asio\join(\HH\Asio\v(Vector{ $this->_void_to_mixed($wait_handle->fail(new CustomException(''))), $wait_handle }));
	}
	public function test_reset_simple(): void {
		$awaitable = async {
			await \HH\Asio\later(); // first notify
			await \HH\Asio\later(); // second notify
		};
		$wait_handle = new ResettableConditionWaitHandle($awaitable->getWaitHandle());
		$vec = \HH\Asio\join(\HH\Asio\v(Vector {
			async {
				await $wait_handle->succeed(42);
				await $wait_handle->succeed(97);
				return null; // so that this is a Vector<Awaitable<?Vector<int>>>>
			},
			async {
				$V = Vector{};
				$next = await $wait_handle;
				$V->add($next);
				$wait_handle->reset();
				await \HH\Asio\later();
				$next = await $wait_handle;
				$V->add($next);
				return $V;
			}
		}));
		$this->assertEquals(Vector{ 42, 97 }, $vec[1]);
	}
	public function test_reset_risky_order(): void {
		$awaitable = async {
			await \HH\Asio\later(); // first notify
			await \HH\Asio\later(); // second notify
		};
		$wait_handle = new ResettableConditionWaitHandle($awaitable->getWaitHandle());
		$vec = \HH\Asio\join(\HH\Asio\v(Vector {
			async {
				await $wait_handle->succeed(42);
				await $wait_handle->succeed(97);
				return null; // so that this is a Vector<Awaitable<?Vector<int>>>>
			},
			async {
				$V = Vector{};
				$next = await $wait_handle;
				$V->add($next);
				await \HH\Asio\later(); // note: the reset is deferred and trying to hand control back to the second `succeed`. We should be able to do this as many times as we want:
				await \HH\Asio\later();
				await \HH\Asio\later();
				$wait_handle->reset();
				$next = await $wait_handle;
				$V->add($next);
				return $V;
			}
		}));
		$this->assertEquals(Vector{ 42, 97 }, $vec[1]);
	}
	public function test_late_notify_exception(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unable to notify ConditionWaitHandle that has already finished');
		$wait_handle = $this->make_empty_wrapper();
		\HH\Asio\join(\HH\Asio\v(Vector{
			async {
				await \HH\Asio\later();
				await \HH\Asio\later();
				await $wait_handle->succeed(42);
				return null;
			},
			$wait_handle
		}));
	}
	// public function test_late_notify_exception_unwrapped(): void {
	// 	$this->expectException(\InvalidArgumentException::class);
	// 	$this->expectExceptionMessage('Unable to notify ConditionWaitHandle that has already finished');
	// 	$wait_handle = $this->make_empty_handle();
	// 	\HH\Asio\join(\HH\Asio\v(Vector{
	// 		async {
	// 			await \HH\Asio\later();
	// 			await \HH\Asio\later();
	// 			$wait_handle->succeed(42);
	// 			return null;
	// 		},
	// 		$wait_handle
	// 	}));
	// }
}