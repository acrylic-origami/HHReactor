<?hh // strict
namespace HHRx\Collection;
final class MarchingLinkedListNode<T> extends LinkedListNode<T> {
	private \HHRx\Wrapper<int> $refcount;
	public function __construct(?MarchingLinkedListNode<T> $next, T $v) {
		parent::__construct($next, $v);
		$this->refcount = new \HHRx\Wrapper(1); // assume created in `LinkedList::make_node`, making one reference
	}
	public function __clone(): void {
		$this->refcount->set($this->refcount->get() + 1); // assume cloned in the process of cloning LinkedList with this as head
	}
	<<__Override>>
	public function next(): ?this {
		// march forward
		$next = $this->next;
		
		// refcount management: this is a hard `next` operation, as opposed to a behavioral `peek`
		$refcount = $this->refcount->get();
		$this->refcount->set($refcount - 1);
		if($refcount - 1 === 0)
			$this->next = null;
		return $next;
	}
	public function has_next(): bool {
		return !is_null($this->next);
	}
}