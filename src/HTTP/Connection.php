<?hh // strict
namespace HHReactor\HTTP;

use HHReactor\BaseProducer;
use HHReactor\Wrapper;
use HHReactor\Collection\Queue;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

abstract class Connection<+TStream as \Stringish> extends BaseProducer<TStream> implements IConnection<TStream> {
	public function __construct(
		private Request $request,
		protected resource $connection) {
		// If I want this to be protected but other BaseProducer-children to maybe have public constructors, then I've gotta do this in each one. Kind of a pain, but (shrug emoji)
		$this->buffer = new Queue();
		$this->running_count = new Wrapper(0);
		// $this->refcount = new Wrapper(1);
		$this->some_running = new Wrapper(new Wrapper(false));
		
	}
	
	public function _attach(): void {}
	
	public function close(): void {}
	
	public async function get_bytes(int $chunk_size): Awaitable<string> {
		$buffer = '';
		$next = '';
		while(strlen($buffer) < $chunk_size) { // includes negative
			$next = await $this->next();
			if(is_null($next)) {
				list($_, $caller) = debug_backtrace(0);
				throw new \RuntimeException(sprintf('Failed to fetch %d bytes for %s', $chunk_size, $caller));
			}
			else
				$buffer .= $next[1];
		}
		return $buffer; // may be > $chunk_size
	}
	
	public function get_request(): Request {
		return $this->request;
	}
	
	public function respond(Response $response): Awaitable<bool> {
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
		
		return $this->write($response_str);
	}
	
	public async function write(string $response): Awaitable<bool> {
		$pos = 0;
		do {
			$status = await stream_await($this->connection, STREAM_AWAIT_WRITE, 0.0);
			if(STREAM_AWAIT_READY === $status) {
				do {
					var_dump(substr($response, $pos, ConnectionIterator::READ_BUFFER_SIZE));
					$bytes_written = fwrite($this->connection, substr($response, $pos, ConnectionIterator::READ_BUFFER_SIZE));
					$pos += $bytes_written;
				}
				while($bytes_written > 0 && $pos < strlen($response));
				
				if($pos === strlen($response))
					return true;
			}
		}
		while(STREAM_AWAIT_READY === $status);
		
		switch($status) {
			case STREAM_AWAIT_CLOSED:
				return false;
			case STREAM_AWAIT_TIMEOUT:
			// FALLTHROUGH
			case STREAM_AWAIT_ERROR:
				throw new \Exception('Stream failed.'); // replace with warning capture
		}
	}
}