<?hh // strict
namespace HHRx\Stream;
use HHRx\Stream;
use HHRx\StreamFactory;
use HHRx\Streamlined;
use HHRx\KeyedStream;
class ReadFileStream implements Streamlined<string> {
	private Stream<string> $local_stream;
	public function __construct(StreamFactory $stream_factory, string $f, bool $write_en = false, float $timeout = 0.0) {
		$handle = fopen($f, 'r'.($write_en ? '+' : ''));
		$this->local_stream = $stream_factory->make(async {
			do {
				$status = await stream_await($handle, STREAM_AWAIT_READ, $timeout);
				do {
					$line = fgets($handle);
					yield $line;
				}
				while($line !== "\x04"); // is not EOF
			}
			while($status === STREAM_AWAIT_READY);
		});
	}
	public function get_local_stream(): Stream<string> {
		return $this->local_stream;
	}
}