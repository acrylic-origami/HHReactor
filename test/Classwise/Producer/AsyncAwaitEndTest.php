<?hh // decl
namespace HHReactor\Test\Classwise\Producer;
use HHReactor\Test\Classwise\ProducerTest;
use HHReactor\Test\CustomException;
use HHReactor\Collection\Producer;
class AsyncAwaitEndTest extends ProducerTest<int> {
	protected function _get_sut(): ProducerVector<int> {
		$source = Vector{ 1, 2, 3 };
		$producer = new Producer(async {
			foreach($source as $v) {
				await \HH\Asio\later();
				yield $v;
			}
			await \HH\Asio\later();
		});
		return shape('sut' => $producer, 'expected' => $source);
	}
}