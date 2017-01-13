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
		stream_set_blocking($server, false);
		$this->local_stream = $stream_factory->make(async {
			do {
				printf("Construct on port %d\n", $port);
				$status = await stream_await($server, STREAM_AWAIT_READ, 0.0);
				if($status === STREAM_AWAIT_READY) {
					$conn = stream_socket_accept($server, 0.0);
					stream_set_blocking($conn, false);
					yield stream_get_contents($conn);
				}
			}
			while($status === STREAM_AWAIT_READY);
		});
	}
	public function get_local_stream(): KeyedStream<mixed, string> {
		return $this->local_stream;
	}
}