<?hh // strict
namespace HHRx\Collection;
<<__ConsistentConstruct>>
// MUST CLONE TO SEPARATE POINTERS
class Producer<+T> implements AsyncIterator<T> {
	private LinkedList<?(mixed, T)> $lag;
	private AsyncIteratorWrapper<T> $iterator;
	private ?Haltable<?(mixed, T)> $haltable = null;
	private bool $finished = false;
	public function __construct(AsyncIterator<T> $raw_iterator) {
		$this->iterator = new AsyncIteratorWrapper($raw_iterator);
		$this->lag = new LinkedList();
	}
	public function __clone(): void {
		$this->lag = clone $this->lag;
	}
	public async function next(): Awaitable<?(mixed, T)> {
		if(!$this->lag->is_empty())
			// fast-forward
			return $this->lag->shift();
		else {
			$this->haltable = new Haltable($this->iterator->next());
			$ret = await $this->haltable;
			$this->lag->add($ret);
			$this->lag->shift(); // broadcast $ret to shared producers, but keep this one at the cutting edge
			return $ret;
		}
	}
	public async function halt(?\Exception $e = null): Awaitable<void> {
		$haltable = $this->haltable;
		invariant(!is_null($haltable), 'Attempted to halt producer before starting iteration.');
		await $haltable->halt($e);
	}
	// public function isFinished(): bool {
	// 	return $this->lag->is_empty() && 
	// }
	public function fast_forward(): Iterator<T> {
		// no risk of "Generator already started" or "Changed during iteration" exceptions, because there are no underlying core Hack collections in LinkedList iterables
		while(!$this->lag->is_empty()) {
			$next = $this->lag->shift();
			if(!is_null($next))
				yield $next[1];
		}
	}
}