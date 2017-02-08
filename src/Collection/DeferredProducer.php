<?hh // strict
namespace HHReactor\Collection;
class DeferredProducer<+T> implements AsyncIterator<T> {
	private ?Producer<T> $producer;
	public function __construct(private (function(): Producer<T>) $factory) {}
	public function next(): Awaitable<?(mixed, T)> {
		$producer = $this->producer;
		if(is_null($producer)) {
			// idempotent setting of producer
			$factory = $this->factory;
			$producer = clone $factory(); // defensive clone, in case the factory is multicasting a Producer pointer
			$this->producer = $producer;
		}
		return $producer->next();
	}
}