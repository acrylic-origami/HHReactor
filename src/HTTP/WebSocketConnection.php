<?hh // strict
namespace HHReactor\HTTP;

use HHReactor\BaseProducer;
use HHReactor\Wrapper;
use HHReactor\Collection\Queue;

abstract class WebSocketConnection extends BaseProducer<WebSocket\Frame> implements IConnection<WebSocket\Frame> {
	const string GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
	
	protected Connection<string> $tap;
	
	public function __construct(Connection<string> $tap) {
		// If I want this to be protected but other BaseProducer-children to maybe have public constructors, then I've gotta do this in each one. Kind of a pain, but (shrug emoji)
		$this->buffer = new Queue();
		$this->running_count = new Wrapper(0);
		// $this->refcount = new Wrapper(1);
		$this->some_running = new Wrapper(new Wrapper(false));
		
		// accept a vanilla connection that has been validated for a WebSocket upgrade and do the upgrade
		$this->tap = clone $tap;
	}
}