<?hh // strict
namespace HHReactor\Producer;
use HHReactor\Collection\Producer;

class HTTPProducer implements AsyncIterator<string> {
	private resource $server;
	public function __construct(int $port, string $host = '127.0.0.1') {
		$this->server = stream_socket_server(sprintf('tcp://%s:%d', $host, $port));
		stream_set_blocking($this->server, false);
	}
	public async function next(): Awaitable<?(mixed, string)> {
		$status = await stream_await($this->server, STREAM_AWAIT_READ, 0.0);
		if($status === STREAM_AWAIT_READY) {
			$conn = stream_socket_accept($this->server, 0.0);
			return stream_get_contents($conn);
		}
		else
			return null;
	}
	public function __destruct(): void {
		stream_socket_shutdown($this->server, STREAM_SHUT_RD);
	}
}