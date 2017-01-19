<?hh // strict
namespace HHRx\Collection;
class SimpleLinkedList<T> extends LinkedList<T, LinkedListNode<T>> {
	public function __construct(Iterable<T> $list = Vector{}) {
		parent::__construct($list, LinkedListNode::class);
	}
	public function getIterator(): Iterator<T>  {
		if(!is_null($this->head)) {
			$node = $this->head;
			while(!is_null($node)) {
				yield $node->get_v();
				$node = $node->next();
			}
		}
	}
}