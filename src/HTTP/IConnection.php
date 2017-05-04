<?hh // strict
namespace HHReactor\HTTP;
use GuzzleHttp\Psr7\Response;
interface IConnection<+TStream as \Stringish> extends AsyncIterator<TStream> {
	public function respond(Response $response): Awaitable<bool>;
	public function write(string $data): Awaitable<bool>;
	public function close(): void;
}