<?hh // strict
namespace HHReactor\Test\Classwise;
use HHReactor\Collection\Producer;
use HHReactor\Test\Classwise\Producer\ProducerVector;
use HHReactor\Test\CustomException;
use PHPUnit\Framework\TestCase;
abstract class ProducerTest<T> extends TestCase {
	abstract protected function _get_sut(): ProducerVector<T>;
	protected function _producer_vector_equality(Producer<T> $producer, \ConstVector<T> $vec, bool $ordered = true): bool {
		$test = \HH\Asio\join(async {
			$accumulator = Vector{};
			foreach($producer await as $v)
				$accumulator->add($v);
			return $accumulator;
		});
		$this->assertEquals($vec, $test, ($ordered ? '' : 'un').'ordered', 0.0, 10, !$ordered);
	}
	protected function _producer_producer_equality(Producer<T> $p1, Producer<T> $p2): bool {
		$v2 = \HH\Asio\join(async {
			$accumulator = Vector{};
			foreach($p2 await as $v)
				$accumulator->add($v);
			return $accumulator;
		});
		$this->_producer_vector_equality($p1, $v2);
	}
	public function test_baseline(): void {
		$sut = $this->_get_sut();
		$this->_producer_vector_equality($sut['sut'], $sut['expected']);
	}
	public function test_clone_independence(): void {
		$sut = $this->_get_sut();
		$cloned_producer = clone $sut['sut'];
		\HH\Asio\join(async {
			foreach($sut['sut'] as $_) {}
		});
		$this->_producer_vector_equality($cloned_producer, $sut['expected']);
	}
}