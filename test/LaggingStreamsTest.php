<?hh
use PHPUnit\Framework\TestCase;
class LaggingStreamsTest extends TestCase {
	private ?StreamFactory $factory = null;
	public function setUp(): void {
		$this->factory = new StreamFactory();
	}
	private async function _wait_produce(int $i): AsyncKeyedIterator<int, int> {
		await HH\Asio\later(); // non-blocking
		yield $i => $i;
	}
	
	public function test(): void {
		$factory = $this->factory;
		invariant(!is_null($factory), '');
		$producers = (new Vector(range(1, 3)))->map((int $v) ==> $factory->make(wait_produce($v)));
		$merged = $factory->merge($producers)->map((int $v) ==> 'MERGED '.$v );
		$double_merged = $factory->merge((Vector{ $merged })->concat($producers))->map((arraykey $v) ==> 'DOUBLE-'.$v );
		$producers->map((KeyedStream<int, int> $stream) ==> $stream->subscribe(fun('var_dump')));
		$merged->subscribe(fun('var_dump'));
		$double_merged->subscribe(fun('var_dump'));
		HH\Asio\join($factory->get_total_awaitable());
		
	}
}