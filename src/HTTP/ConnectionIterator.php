<?hh // strict
namespace HHReactor\HTTP;
use HHReactor\BaseProducer;
use HHReactor\Wrapper;
use HHReactor\Collection\Queue;

use GuzzleHttp\Psr7\Request;
use function GuzzleHttp\Psr7\parse_request;

class ConnectionIterator extends BaseProducer<Connection<string>> {
	private resource $server;
	const int READ_BUFFER_SIZE = 8192;
	public function __construct(int $port, string $host = '127.0.0.1') {
		// If I want this to be protected but other BaseProducer-children to maybe have public constructors, then I've gotta do this in each one. Kind of a pain, but (shrug emoji)
		$this->buffer = new Queue();
		$this->running_count = new Wrapper(0);
		// $this->refcount = new Wrapper(1);
		$this->some_running = new Wrapper(new Wrapper(false));
		
		set_error_handler(($errno, $errstr, $errfile, $errline, $errcontext) ==> {
			if($errfile !== __FILE__)
				return false;
			else
				throw new \ErrorException($errstr, $errno, E_ERROR, $errfile, $errline);
		}, E_WARNING);
		$this->server = stream_socket_server(sprintf('tcp://%s:%d', $host, $port));
		stream_set_blocking($this->server, false);
	}
	public function _attach(): void {}
	public async function _produce(): Awaitable<?(mixed, Connection<string>)> {
		do {
			$status = await stream_await($this->server, STREAM_AWAIT_READ, 0.0);
			if($status === STREAM_AWAIT_READY) {
				$conn = stream_socket_accept($this->server, 0.0);
				stream_set_blocking($conn, false);
				// stream_set_read_buffer($conn, self::READ_BUFFER_SIZE);
				do {
					$status = await stream_await($conn, STREAM_AWAIT_READ, 0.0);
					if($status === STREAM_AWAIT_READY) {
						$header = '';
						$buffer = tuple(fread($conn, self::READ_BUFFER_SIZE), '');
						do {
							$header .= $buffer[1]; // tack on the outgoing buffer
							$header_end = strpos($buffer[1].$buffer[0], "\r\n\r\n");
							$buffer = tuple(fread($conn, self::READ_BUFFER_SIZE), $buffer[0]); // no performance cost afaik
						}
						while(!feof($conn) && false === $header_end);
						if(false !== $header_end) {
							$total_buffer = $buffer[1] . $buffer[0];
							
							$request = parse_request($header . substr($total_buffer, 0, $header_end));
							$headers = $request->getHeaders();
							if(array_key_exists('Content-Length', $headers))
								return tuple(null, new ContentLengthConnection(
									$request,
									$conn,
									intval($headers['Content-Length']) - (strlen($total_buffer) - $header_end),
									substr($total_buffer, $header_end)
									));
							else
								return tuple(null, new BodylessConnection($request, $conn));
						}
					}
				}
				while($status === STREAM_AWAIT_READY);
			}
		}
		while($status === STREAM_AWAIT_READY);
		
		switch($status) {
			case STREAM_AWAIT_CLOSED:
				return null;
			case STREAM_AWAIT_TIMEOUT:
			// FALLTHROUGH
			case STREAM_AWAIT_ERROR:
				throw new \Exception('Stream failed.'); // replace with warning capture
		}
	}
}