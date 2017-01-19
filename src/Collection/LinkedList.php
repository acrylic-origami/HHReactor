<?hh // strict
namespace HHRx\Collection;
abstract class LinkedList<T, TNode as LinkedListNode<T>> { // extends WeakArtificialKeyedIterable<mixed, T>
	protected Wrapper<?TNode> $head, $tail;
	public function __construct(Iterable<T> $list, protected classname<TNode> $node_class) {
		$this->tail = new Wrapper(null);
		$this->head = new Wrapper($this->_build_list($list->getIterator()));
	}
	private function _build_list(Iterator<T> $iterator): ?TNode {
		if($iterator->valid()) {
			$val = $iterator->current();
			$iterator->next();
			$next = $this->_build_list($iterator);
			if(is_null($next)) {
				$this->tail->set($this->make_node(null, $val));
				return $this->tail->get();
			}
			return $this->make_node($next, $val);
		}
		else {
			return null;
		}
	}
	public function is_empty(): bool {
		return is_null($this->head);
	}
	protected function make_node(?TNode $next, T $v): TNode {
		$node_class = $this->node_class;
		/* HH_FIXME[4110] Because all implementations pass $node_class with exactly TNode::class, this is safe. Waiting for equality type constraints to truly enforce this. */
		return new $node_class($next, $v);
	}
	public function add(T $incoming): void {
		$tail = $this->tail;
		$next = $this->make_node(null, $incoming);
		if(is_null($tail)) {
			$this->head->set($next);
		}
		else {
			/* HH_FIXME[4110] Because all implementations pass $node_class with exactly TNode::class, this is safe. Waiting for equality type constraints to truly enforce this. */
			$tail->set_next($next);
		}
		// advance tail pointer
		$this->tail->set($next);
	}
}