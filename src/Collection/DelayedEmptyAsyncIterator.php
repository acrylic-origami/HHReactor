<?hh // strict
namespace HHReactor\Asio;
class DelayedEmptyAsyncIterator<+T> implements AsyncIterator<T> {
	public function __construct(private Awaitable<mixed> $delay) {}
	public async function next(): Awaitable<?(mixed, T)> {
		await $this->delay;
		return null;
	}
}