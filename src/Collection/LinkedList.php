<?hh // strict
namespace HHRx\Collection;
class LinkedList<T> extends WeakArtificialKeyedIterable<mixed, T> {
	private ?LinkedListNode<T> $head, $tail = null;
	private int $count = 0;
	public function __construct(Iterable<T> $list) {
		$this->head = $this->_build_list($list->getIterator());
	}
	private function _build_list(Iterator<T> $iterator): ?LinkedListNode<T> {
		if($iterator->valid()) {
			$val = $iterator->current();
			$iterator->next();
			$next = $this->_build_list($iterator);
			if(is_null($next)) {
				$this->tail = new LinkedListNode(null, $val);
				return $this->tail;
			}
			return new LinkedListNode($next, $val);
		}
		else {
			return null;
		}
	}
	public function count(): int {
		return $this->count;
	}
	public function add(T $incoming): void {
		$tail = $this->tail;
		$next = new LinkedListNode(null, $incoming);
		if(is_null($tail)) {
			$this->head = $next;
		}
		else {
			$tail->next = $next;
		}
		$this->tail = $next;
		$this->count++;
	}
	public function getIterator(): KeyedIterator<mixed, T> {
		if(!is_null($this->head)) {
			$node = $this->head;
			while(!is_null($node)) {
				yield $node->v;
				$node = $node->next;
			}
		}
	}
}