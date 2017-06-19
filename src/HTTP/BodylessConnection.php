<?hh // strict
namespace HHReactor\HTTP;
use GuzzleHttp\Psr7\Request;
use HHReactor\Collection\EmptyAsyncIterator;
class BodylessConnection extends Connection {
	public async function _produce(): Awaitable<?(mixed, string)> {
		return null;
	}
}