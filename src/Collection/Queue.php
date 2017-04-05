<?hh // strict
namespace HHReactor\Collection;
use HHReactor\Wrapper;
class Queue<T> extends AppendOnlyQueue<T> { // extends WeakArtificialKeyedIterable<mixed, T>
	public function shift(): T {
		$head = $this->head->get();
		if(is_null($head))
			throw new \RuntimeException('Tried to `shift` an empty `LinkedList`.');
		$next = $head->next();
		if($this->_empty && is_null($next))
			throw new \RuntimeException('Tried to `shift` an empty `LinkedList`.');
		
		if(!$this->_started) {
			// we could clone the head every time, but this saves some overhead
			$this->_started = true;
			$this->head = clone $this->head;
		}
		
		if($this->_empty) {
			invariant(!is_null($next), 'Logical impossibility by empty check');
			$head = $next;
			$next = $next->next();
			$this->head->set($head);
		}
		
		if(!is_null($next))
			$this->head->set($next);
		else
			$this->_empty = true;
		
		return $head->get_v();
	}
}