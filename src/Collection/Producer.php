<?hh // strict
namespace HHRx\Collection;
<<__ConsistentConstruct>>
// MUST CLONE TO SEPARATE POINTERS
class Producer<+T> implements AsyncIterator<T> {
	private Vector<?(mixed, T)> $stash = Vector{};
	private int $pointer = 0;
	private AsyncIteratorWrapper<T> $iterator;
	private ?Haltable<?(mixed, T)> $haltable = null;
	public function __construct(AsyncIterator<T> $raw_iterator) {
		$this->iterator = new AsyncIteratorWrapper($raw_iterator);
	}
	public async function next(): Awaitable<?(mixed, T)> {
		if($this->pointer < $this->stash->count()) {
			return $this->stash[$this->pointer++];
		}
		if($this->isFinished())
			// Protecting against bad calls
			return null;
			
		$awaitable = $this->iterator->next();
		// $v = await $awaitable;
		$this->haltable = new Haltable($awaitable);
		$v = await $this->haltable;
		
		if($this->pointer === $this->stash->count())
			$this->stash->add($v);
		$this->pointer++;
		return $v;
	}
	public async function halt(?\Exception $e = null): Awaitable<void> {
		$haltable = $this->haltable;
		invariant(!is_null($haltable), 'Attempted to halt producer before starting iteration.');
		await $haltable->halt($e);
	}
	public function isFinished(): bool {
		return $this->stash->count() > 0 && is_null($this->stash->lastValue());
	}
	public function fast_forward(): Iterator<T> {
		for(; $this->pointer < $this->stash->count(); $this->pointer++) {
			$stashed = $this->stash[$this->pointer++];
			if(!is_null($stashed))
				yield $stashed[1];
		}
	}
}