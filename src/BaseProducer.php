<?hh // strict
namespace HHReactor;
use HH\Asio\AsyncCondition;
use HHReactor\Wrapper;
abstract class BaseProducer<+T> implements AsyncIterator<T> {
	// protected Wrapper<int> $refcount;
	protected Wrapper<int> $running_count;
	private bool $this_running = false;
	protected Wrapper<Wrapper<bool>> $some_running;
	/* HH_FIXME[4120] Use only in object-protected ways. */
	protected Collection\Queue<T> $buffer;
	
	public function __clone(): void {
		$this->buffer = clone $this->buffer;
		// $this->refcount->v++;
	}
	
	protected function _detach(): void {
		if($this->this_running) {
			$this->running_count->v--;
			if($this->running_count->get() === 0) {
				$this->some_running->get()->set(false);
			}
		}
	}
	abstract protected function _attach(): void;
	abstract protected function _produce(): Awaitable<?(mixed, T)>;
	public function __destruct(): void {
		// $this->refcount->v--;
		$this->_detach();
	}
	public async function next(): Awaitable<?(mixed, T)> {
		if(!$this->this_running) {
			$this->this_running = true;
			if(!$this->some_running->get()->get()) {
				$this->some_running->set(new Wrapper(true));
				$this->_attach();
			}
			$this->running_count->v++;
		}
		if(!$this->buffer->is_empty())
			$ret = tuple(null, $this->buffer->shift());
		else
			$ret = await $this->_produce();
		
		if(!is_null($ret) && $ret[1] instanceof BaseProducer) // for Producer<Producer<T>>s
			return tuple(null, clone $ret[1]);
		else
			return $ret;
	}
	// /* HH_FIXME[4120] Object-protected uses only */
	// protected function soft_next(AsyncCondition<?BaseProducer<T>> $condition): Awaitable<?(mixed, T)> {
	// 	// not super keen on this being in the parent class... but for now it's the most convenient
	// 	return $this->next();
	// }
}