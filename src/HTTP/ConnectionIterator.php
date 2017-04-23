<?hh // strict
namespace HHReactor\HTTP;
use HHReactor\Collection\Producer;

use GuzzleHttp\Psr7\Request;
use function GuzzleHttp\Psr7\parse_request;

/* HH_FIXME[2049] I think Request needs to be strict or non-PHP */
class ConnectionIterator implements AsyncIterator<(Request, AsyncIterator<string>)> {
	private resource $server;
	const int READ_BUFFER_SIZE = 8192;
	public function __construct(int $port, string $host = '127.0.0.1') {
		$this->server = stream_socket_server(sprintf('tcp://%s:%d', $host, $port));
		stream_set_blocking($this->server, false);
	}
	/* HH_FIXME[2049] I think Request needs to be strict or non-PHP */
	public async function next(): Awaitable<?(mixed, (Request, AsyncIterator<string>))> {
		do {
			$status = await stream_await($this->server, STREAM_AWAIT_READ, 0.0);
			if($status === STREAM_AWAIT_READY) {
				$conn = stream_socket_accept($this->server, 0.0);
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
							return tuple(
								null, tuple(
									parse_request($header . substr($total_buffer, 0, $header_end)),
									async {
										yield substr($total_buffer, $header_end); // yield initial fragment
										
										do {
											$status = await stream_await($conn, STREAM_AWAIT_READ, 0.0);
											// yield the rest of the body
											if($status === STREAM_AWAIT_READY)
												do {
													$buffer = fread($conn, self::READ_BUFFER_SIZE);
													yield $buffer;
												}
												while(strpos($buffer, 0x04) !== false); // look for EOF
										}
										while(!feof($conn) && $status === STREAM_AWAIT_READY);
									}
								)
							);
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
	public function __destruct(): void {
		stream_socket_shutdown($this->server, STREAM_SHUT_RD);
	}
}