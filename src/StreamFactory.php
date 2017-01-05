<?hh // strict
namespace HHRx;
class StreamFactory {
	public function __construct(private TotalAwaitable $total_awaitable) {}
	public function make<Tk, T>(AsyncKeyedIterator<Tk, T> $producer): KeyedStream<Tk, T> {
		$stream = new KeyedStream($producer);
		$this->total_awaitable->add(async {
			foreach($producer await as $_) {}
		});
		return $stream;
	}
}