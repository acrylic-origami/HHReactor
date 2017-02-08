<?hh // strict
namespace HHReactor\Collection;
class EmptyAsyncIterator<+T> implements AsyncIterator<T> {
	public async function next(): Awaitable<?(mixed, T)> {
		return null;
	}
}