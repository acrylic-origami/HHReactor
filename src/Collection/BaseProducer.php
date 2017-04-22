<?hh // strict
namespace HHReactor\Collection;
use HH\Asio\AsyncCondition;
use HHReactor\Wrapper;
abstract class BaseProducer<+T> implements AsyncIterator<T> {
	protected Wrapper<int> $refcount;
	private bool $this_running = false;
	protected Wrapper<bool> $some_running;
	
	protected function detach(): void {
		if($this->this_running) {
			$this->refcount->v--;
			if($this->refcount->get() === 0)
				$this->some_running->set(false);
		}
	}
	abstract protected function _attach(): void;
	abstract protected function _next(): Awaitable<?(mixed, T)>;
	public function __destruct(): void {
		$this->detach();
	}
	public function next(): Awaitable<?(mixed, T)> {
		if(!$this->this_running) {
			$this->this_running = true;
			if(!$this->some_running->get()) {
				$this->some_running = new Wrapper(true);
			}
			$this->refcount->v++;
			$this->_attach();
		}
		return $this->_next();
	}
	// /* HH_FIXME[4120] Object-protected uses only */
	// protected function soft_next(AsyncCondition<?BaseProducer<T>> $condition): Awaitable<?(mixed, T)> {
	// 	// not super keen on this being in the parent class... but for now it's the most convenient
	// 	return $this->next();
	// }
}