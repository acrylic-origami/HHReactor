<?hh // strict
namespace HHReactor\HTTP;

use GuzzleHttp\Psr7\Request;

class ContentLengthConnection extends Connection {
	public function __construct(
		Request $request,
		resource $stream,
		private int $remaining_length,
		private string $initial) {
		parent::__construct($request, $stream);
	}
	<<__Override>>
	protected async function _produce(): Awaitable<?(mixed, string)> {
		$initial = $this->initial;
		if($initial !== '') {
			$this->initial = '';
			return tuple(null, $initial);
		}
		elseif($this->remaining_length > 0) {
			$next = await parent::_produce();
			if(is_null($next))
				return null;
			else {
				$this->remaining_length -= strlen($next[1]);
				return $next;
			}
		}
		else
			return null;
	}
}