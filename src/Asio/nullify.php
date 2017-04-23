<?hh // strict
namespace HHReactor\Asio;
function nullify<T>(Awaitable<mixed> $incoming): Awaitable<?T> {
	return async {
		await $incoming;
		return null;
	};
}