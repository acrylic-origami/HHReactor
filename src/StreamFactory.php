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
	public function get_total_awaitable(): Awaitable<void> { // not totally keen on this public getter
		return $this->total_awaitable->get_awaitable();
	}
}