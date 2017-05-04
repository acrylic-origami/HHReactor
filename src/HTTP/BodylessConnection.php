<?hh // strict
namespace HHReactor\HTTP;
class BodylessConnection extends Connection<string> {
	public async function _produce(): Awaitable<?(mixed, string)> {
		return null;
	}
}