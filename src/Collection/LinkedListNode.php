<?hh // strict
namespace HHRx\Collection;
<<__ConsistentConstruct>>
class LinkedListNode<T> {
	public function __construct(
		protected ?this $next,
		protected T $v
	) {}
	public function get_v(): T {
		return $this->v;
	}
	public function next(): ?this {
		return $this->next;
	}
	public function set_next(this $next): void {
		// note non-null `this`: irreversible (except for ``RefLinkedListNode::next()` can nullify `$this->next`)
		$this->next = $next;
	}
	public function getIterator(): KeyedIterator<mixed, T> {
		if(!is_null($this->next))
			foreach($this->next->getIterator() as $v)
				yield $v;
		yield $this->v;
	}
}