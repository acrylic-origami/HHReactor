<?hh // strict
namespace HHRx\Collection;
<<__ConsistentConstruct>>
// MUST CLONE TO SEPARATE POINTERS
class KeyedProducer<+Tk, +Tv> implements AsyncKeyedIterator<Tk, Tv> {
	private Vector<?(Tk, Tv)> $stash = Vector{};
	public int $pointer = 0;
	private AsyncKeyedIteratorWrapper<Tk, Tv> $iterator;
	public function __construct(AsyncKeyedIterator<Tk, Tv> $raw_iterator) {
		$this->iterator = new AsyncKeyedIteratorWrapper($raw_iterator);
	}
	public function get_stash(): \ConstVector<?(Tk, Tv)> {
		return $this->stash;
	}
	public async function next(): Awaitable<?(Tk, Tv)> {
		// var_dump($this->stash);
		if($this->pointer < $this->stash->count()) {
			// var_dump($this->stash);
			return $this->stash[$this->pointer++];
		}
		if($this->isFinished())
			// Protecting against bad calls
			return null;
			
		$v = await $this->iterator->next();
		if($this->pointer === $this->stash->count())
			$this->stash->add($v);
		$this->pointer++;
		return $v;
	}
	public function isFinished(): bool {
		return $this->stash->count() > 0 && is_null($this->stash->lastValue());
	}
	public function fast_forward(): KeyedIterator<Tk, Tv> {
		for(; $this->pointer < $this->stash->count(); $this->pointer++) {
			$stashed = $this->stash[$this->pointer++];
			if(!is_null($stashed))
				yield $stashed[0] => $stashed[1];
		}
	}
}