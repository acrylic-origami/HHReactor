<?hh // strict
namespace HHReactor\Collection;
use HHReactor\Wrapper;
class Queue<T> extends AppendOnlyQueue<T> { // extends WeakArtificialKeyedIterable<mixed, T>
	public function shift(): T {
		$head = $this->head->get();
		if(is_null($head))
			throw new \RuntimeException('Tried to `shift` an empty `LinkedList`.');
		$next = $head->next();
		
		if(!$this->_started) {
			// we could clone the head every time, but this saves some overhead
			$this->_started = true;
			$this->head = clone $this->head;
		}
		
		// if($this->_stopped && is_null($next))
		// 	// still at end: keep returning null
		// 	return null;
		if(!is_null($next)) { // elseif
			// new elements added, advance head
			$this->head->set($next);
			if($this->_empty)
				// previously "empty": the next element is the one we should pop
				$head = $next;
		}
		$this->_empty = is_null($head->next());
		
		return $head->get_v();
	}
}