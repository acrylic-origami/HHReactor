<?hh // strict
namespace HHRx\Collection;
class EmptyKeyedIterator<+Tk, +Tv> implements AsyncKeyedIterator<Tk, Tv> {
	public function __construct(public string $msg = '') {} 
	public function next(): Awaitable<?(Tk, Tv)> {
		throw new \Exception($this->msg);
	}
}