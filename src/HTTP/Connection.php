<?hh // strict
namespace HHReactor\HTTP;

use HHReactor\Producer;
use HHReactor\Wrapper;
use HHReactor\Collection\Queue;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

abstract class Connection extends Producer<string> {
	const READ_BUFFER_SIZE = 8192;
	
	public function __construct(
		protected Request $request,
		private resource $stream
	) {
		parent::__construct(Vector{ async ($_) ==> {
			for(
				$status = await stream_await($stream, STREAM_AWAIT_READ, 0.0);
				$status === STREAM_AWAIT_READY;
				$status = await stream_await($stream, STREAM_AWAIT_READ, 0.0)
			) {
				yield fread($stream, self::READ_BUFFER_SIZE);
			}
				
			if($status !== STREAM_AWAIT_CLOSED)
				throw new \Exception('Stream failed.');
		} });
	}
	
	public async function get_bytes(int $num_bytes): Awaitable<string> {
		$ret = '';
		for(
			$ret = $next = '';
			strlen($ret) < $num_bytes && !is_null($next);
			$ret .= $next
		) {}
			
		if(strlen($ret) < $num_bytes)
			throw new \LengthException("Stream closed before $num_bytes could be extracted.");
		else
			return $ret;
	}
	
	public function close(): void {
		// questioning if I should even break this method out, or if I should let the GC do the work and let functions fail
		socket_close($this->stream);
	}
	
	public async function write(string $response): Awaitable<int> {
		$pos = 0;
		do {
			$status = await stream_await($this->stream, STREAM_AWAIT_WRITE, 0.0);
			if(STREAM_AWAIT_READY === $status) {
				do {
					// var_dump(substr($response, $pos, self::READ_BUFFER_SIZE));
					$bytes_written = fwrite($this->stream, substr($response, $pos, self::READ_BUFFER_SIZE));
					$pos += $bytes_written;
				}
				while($bytes_written > 0 && $pos < strlen($response));
				
				if($pos === strlen($response))
					return $pos;
			}
		}
		while(STREAM_AWAIT_READY === $status);
		
		switch($status) {
			case STREAM_AWAIT_CLOSED:
				return $pos;
			case STREAM_AWAIT_TIMEOUT:
			// FALLTHROUGH
			case STREAM_AWAIT_ERROR:
				throw new \Exception('Stream failed.'); // replace with warning capture
		}
	}
	
	public async function respond(Response $response): Awaitable<bool> {
		$response_str = '';
		
		$response_str .= sprintf(
			"%s %s %s\r\n",
			$response->getProtocolVersion(),
			$response->getStatusCode(),
			$response->getReasonPhrase()
		);
		$response_str .= sprintf(
			"%s\r\n\r\n",
			implode("\r\n", (new Map($response->getHeaders()))->mapWithKey(($k, $v) ==> sprintf('%s: %s', $k, $v)))
		);
		$response_str .= $response->getBody();
		
		$bytes_written = await $this->write($response_str);
		return $bytes_written === strlen($response_str);
	}
	
	public function get_request(): Request {
		return $this->request;
	}
}