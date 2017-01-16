<?hh // strict
namespace HHRx\Collection;
class EmptyKeyedIterator<+Tk, +Tv> implements AsyncKeyedIterator<Tk, Tv> {
	public function __construct() {} 
	public async function next(): Awaitable<?(Tk, Tv)> {
		return null;
	}
}