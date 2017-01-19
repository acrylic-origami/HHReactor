<?hh // strict
namespace HHRx\Collection;
class MarchingLinkedList<T> extends LinkedList<T, MarchingLinkedListNode<T>> {
	public function __construct(Iterable<T> $list = Vector{}) {
		parent::__construct($list, MarchingLinkedListNode::class);
	}
	public function __clone(): void {
		$this->head = clone $this->head;
	}
	// public function getIterator(): Iterator<T> {
	// 	$head = $this->head;
	// 	while(!is_null($head)) {
	// 		yield $head->get_v();
	// 		// marching operation
	// 		$this->head = $head->next();
	// 	}
	// }
	public function is_empty(): bool {
		return is_null($this->head);
	}
	public function next(): ?T {
		$head = $this->head;
		if(is_null($head))
			// throw new \RuntimeException('`MarchingLinkedList` is empty or finished.');
			return null;
		$v = $head->get_v();
		$next = $head->next();
		if(!is_null($next))
			$this->head = $next;
		else
			$this->head = $this->tail = null;
		return $v;
	}
}