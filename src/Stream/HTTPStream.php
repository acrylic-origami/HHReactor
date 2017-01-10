<?hh // strict
namespace HHRx\Stream;
use HHRx\Stream;
use HHRx\StreamFactory;
use HHRx\KeyedStream;
use HHRx\Streamlined;

class HTTPStream implements Streamlined<string> {
	private Stream<string> $local_stream;
	public function __construct(StreamFactory $stream_factory, private int $port, private string $host = '127.0.0.1') {
		$server = stream_socket_server(sprintf('tcp://%s:%d', $host, $port));
		stream_set_blocking($server, 0);
		$this->local_stream = $stream_factory->make(async {
			do {
				$accepted_stream = stream_socket_accept($server);
				stream_set_blocking($accepted_stream, 0);
				$status = await stream_await($accepted_stream, STREAM_AWAIT_READ, 0.0);
				if($status === STREAM_AWAIT_READY) {
					yield stream_get_contents($accepted_stream);
				}
			}
			while($status === STREAM_AWAIT_READY);
		});
	}
	public function get_local_stream(): KeyedStream<mixed, string> {
		return $this->local_stream;
	}
}