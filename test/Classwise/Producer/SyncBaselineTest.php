<?hh // strict
namespace HHReactor\Test\Classwise\Producer;
use HHReactor\Test\Classwise\ProducerTest;
use HHReactor\Collection\Producer;
use HHReactor\Test\CustomException;
class SyncBaselineTest extends ProducerTest<int> {
	protected function _get_sut(): ProducerVector<int> {
		$source = Vector{ 1, 2, 3 };
		$producer = new Producer(async {
			foreach($source as $v)
				yield $v;
		});
		return shape('sut' => $producer, 'expected' => $source);
	}
}