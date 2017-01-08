<?hh // strict
namespace HHRx\Collection;
class LinkedListNode<T> {
	public function __construct(
		public ?LinkedListNode<T> $next,
		public T $v
	){}
	public function getIterator(): KeyedIterator<mixed, T> {
		if(!is_null($this->next))
			foreach($this->next->getIterator() as $v)
				yield $v;
		yield $this->v;
	}
}