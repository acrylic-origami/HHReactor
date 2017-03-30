<?hh // strict
namespace HHReactor\Asio;
/**
 * Transform an `Awaitable` to a `T`-valued iterator that does not emit anything.
 */
class DelayedEmptyAsyncIterator<+T> implements AsyncIterator<T> {
	public function __construct(private Awaitable<mixed> $delay) {}
	/**
	 * Wait for this underlying `Awaitable` to resolve, then end the iterator.
	 * @return - Always resolve to `null`.
	 */
	public async function next(): Awaitable<?(mixed, T)> {
		await $this->delay;
		return null;
	}
}