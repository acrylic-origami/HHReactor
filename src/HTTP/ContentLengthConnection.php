<?hh // strict
namespace HHReactor\HTTP;

use GuzzleHttp\Psr7\Request;

class ContentLengthConnection extends Connection<string> {
	public function __construct(
		Request $request,
		resource $connection,
		private int $remaining_length,
		private string $initial
		) {
		parent::__construct($request, $connection);
	}
	
	public async function _produce(): Awaitable<?(mixed, string)> {
		if($this->initial !== '') {
			$initial = $this->initial;
			$this->initial = '';
			return tuple(null, substr($initial, 0, strlen($initial) + $this->remaining_length)); // reconstruct content-length
		}
		if($this->remaining_length > 0) {
			$status = await stream_await($this->connection, STREAM_AWAIT_READ, 0.0);
			switch($status) {
				case STREAM_AWAIT_READY:
					$incoming = fread($this->connection, min(ConnectionIterator::READ_BUFFER_SIZE, $this->remaining_length));
					$this->remaining_length -= strlen($incoming);
					return tuple(null, $incoming);
				case STREAM_AWAIT_CLOSED:
					return null;
				case STREAM_AWAIT_TIMEOUT:
				// FALLTHROUGH
				case STREAM_AWAIT_ERROR:
					throw new \Exception('Stream failed.'); // replace with warning capture
			}
		}
		return null;
	}
}