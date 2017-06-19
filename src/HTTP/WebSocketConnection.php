<?hh // strict
namespace HHReactor\HTTP;

use HHReactor\Producer;
use HHReactor\Wrapper;
use HHReactor\Collection\Queue;

abstract class WebSocketConnection {
	const string GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
	protected Connection $connection;
}