<?hh // strict
namespace HHReactor\HTTP;
use HHReactor\BaseProducer;
use HHReactor\Wrapper;
use HHReactor\Collection\Queue;

use GuzzleHttp\Psr7\Request;
use function GuzzleHttp\Psr7\parse_request;

async function iterate_header(resource $conn, int $READ_BUFFER_SIZE = 8192): Awaitable<Connection> {
	// stream_set_read_buffer($conn, self::READ_BUFFER_SIZE);
	$header = '';
	do {
		$status = await stream_await($conn, STREAM_AWAIT_READ, 0.0);
		if($status === STREAM_AWAIT_READY) {
			for(; !feof($conn); $header .= fread($conn, $READ_BUFFER_SIZE)) {
				$header_end = strpos($header, "\r\n\r\n"); // lazy and not the most performant way to check for header end, but expect headers to be pretty short anyways
				if(false !== $header_end) {
					$request = parse_request(substr($header, 0, $header_end));
					$headers = $request->getHeaders();
					if(array_key_exists('Content-Length', $headers))
						return new ContentLengthConnection(
							$request,
							$conn,
							intval($headers['Content-Length']) - (strlen($header) - $header_end),
							substr($header, $header_end + strlen("\r\n\r\n"))
						);
					else
						return new BodylessConnection($request, $conn);
				}
			}
		}
	}
	while($status === STREAM_AWAIT_READY);
	
	switch($status) {
		case STREAM_AWAIT_CLOSED:
			throw new \UnexpectedValueException('Stream closed before all headers were sent.');
		case STREAM_AWAIT_ERROR:
		// FALLTHROUGH
		case STREAM_AWAIT_TIMEOUT:
			throw new \Exception('Stream errored and closed before all headers were sent.');
	}
}
async function iterate_connection(int $port, string $host = '127.0.0.1', int $READ_BUFFER_SIZE = 8192): AsyncIterator<Awaitable<Connection>> {
	set_error_handler(($errno, $errstr, $errfile, $errline, $errcontext) ==> {
		if($errfile !== __FILE__)
			return false;
		else
			throw new \ErrorException($errstr, $errno, E_ERROR, $errfile, $errline);
	}, E_WARNING);
	$server = stream_socket_server(sprintf('tcp://%s:%d', $host, $port));
	stream_set_blocking($server, false);
	
	do {
		$status = await stream_await($server, STREAM_AWAIT_READ, 0.0);
		if($status === STREAM_AWAIT_READY) {
			// accept connection and read request
			$conn = stream_socket_accept($server, 0.0);
			stream_set_blocking($conn, false);
			yield iterate_header($conn, $READ_BUFFER_SIZE);
			// split header parsing from this main connection handler to avoid DoS by something Slow Loris-esque
		}
	}
	while($status === STREAM_AWAIT_READY);

	switch($status) {
		case STREAM_AWAIT_CLOSED:
			return;
		case STREAM_AWAIT_TIMEOUT:
		// FALLTHROUGH
		case STREAM_AWAIT_ERROR:
			throw new \Exception('Stream failed.'); // replace with warning capture
	}
}