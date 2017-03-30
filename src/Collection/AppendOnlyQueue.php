<?hh // strict
namespace HHReactor\Collection;
use HHReactor\Wrapper;
class AppendOnlyQueue<T> { // extends WeakArtificialKeyedIterable<mixed, T>
	protected Wrapper<?LinkedListNode<T>> $head; // head pointer should always be cloned when list is cloned
	protected Wrapper<?LinkedListNode<T>> $tail; // tail pointer is wrapped for sharing when cloned
	protected bool $_empty = false; // initially false for starting condition
	protected bool $_started = false;
	public function __construct(Iterable<T> $list = Vector{}) {
		$head = null;
		$prev = null;
		$iterator = $list->getIterator();
		while($iterator->valid()) {
			$next = new LinkedListNode(null, $iterator->current());
			if(is_null($head)) {
				$head = $next;
				$prev = $head;
			}
			invariant(!is_null($prev), 'Can\'t be null here: set on first iteration and never unset.');
			$prev->set_next($next);
			$prev = $next;
		}
		
		$this->tail = new Wrapper($prev);
		$this->head = new Wrapper($head);
	}
	public function __clone(): void {
		// Separate pointers to the head of the queue, but keep sharing the tail pointer
		if(!is_null($this->head->get()))
			$this->head = clone $this->head;
	}
	public function is_empty(): bool {
		$head = $this->head->get();
		return is_null($head) || ($this->_empty && is_null($head->next())); // crucial to look at `head` for emptiness: tail is shared and is never unset.
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
	// public static function merge(AppendOnlyQueue<T> ... $queues): AppendOnlyQueue<T> {
	// 	if(count($queues) === 0)
	// 		throw new \BadMethodCallException(sprintf('Must merge at least two queues in %s.', __METHOD__));
		
	// 	$ret = clone $queues[0];
	// 	$ret->tail = clone $ret->tail; // exception to the typical cloning process: we don't want to extend shared tails when merging
	// 	for($i = 0; $i < count($queues) - 1; $i++) {
	// 		$ret->tail->get()->set_next($queues[$i+1]->head);
	// 		$ret->tail = $queues[$i+1]->tail;
	// 	}
	// 	return $ret;
	// }
}