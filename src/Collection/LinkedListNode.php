<?hh // strict
namespace HHReactor\Collection;
<<__ConsistentConstruct>>
class LinkedListNode<T> {
	public function __construct(
		protected ?LinkedListNode<T> $next,
		protected T $v
	) {}
	public function get_v(): T {
		return $this->v;
	}
	public function next(): ?LinkedListNode<T> {
		return $this->next;
	}
	public function set_next(LinkedListNode<T> $next): void {
		// note non-null `this`: irreversible
		$this->next = $next;
	}
	// public function getIterator(): KeyedIterator<mixed, T> {
	// 	if(!is_null($this->next))
	// 		foreach($this->next->getIterator() as $v)
	// 			yield $v;
	// 	yield $this->v;
	// }
}