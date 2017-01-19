<?hh // strict
namespace HHRx\Collection;
use HHRx\Wrapper;
class LinkedList<T> { // extends WeakArtificialKeyedIterable<mixed, T>
	protected Wrapper<?LinkedListNode<T>> $head; // head pointer should always be cloned when list is cloned
	protected Wrapper<?LinkedListNode<T>> $tail; // tail pointer is wrapped for sharing when cloned
	private bool $_nulled = true;
	public function __construct(Iterable<T> $list = Vector{}) {
		$this->tail = new Wrapper(null);
		$this->head = new Wrapper($this->_build_list($list->getIterator()));
	}
	public function __clone(): void {
		if(!is_null($this->head->get()))
			$this->head = clone $this->head;
	}
	private function _build_list(Iterator<T> $iterator): ?LinkedListNode<T> {
		if($iterator->valid()) {
			$val = $iterator->current();
			$iterator->next();
			$next = $this->_build_list($iterator);
			if(is_null($next)) {
				$this->tail->set(new LinkedListNode(null, $val));
				return $this->tail->get();
			}
			return new LinkedListNode($next, $val);
		}
		else {
			return null;
		}
	}
	public function is_empty(): bool {
		return is_null($this->head->get()); // crucial to look at `head` for emptiness: tail is shared and is never unset.
	}
	public function shift(): T {
		$head = $this->head->get();
		if(is_null($head))
			throw new \RuntimeException('Tried to `shift` an empty `LinkedList`.');
		$next = $head->next();
		
		if($this->_nulled)
			$this->head = clone $this->head;
		$this->_nulled = is_null($next);
		$this->head->set($next);
		return $head->get_v();
	}
	public function add(T $incoming): void {
		$tail = $this->tail->get();
		$head = $this->head->get();
		
		$next = new LinkedListNode(null, $incoming);
		
		if(!is_null($tail))
			$tail->set_next($next);
		
		if(is_null($head))
			$this->head->set($next);
		
		// advance tail pointer
		$this->tail->set($next);
	}
}