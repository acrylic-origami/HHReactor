<?hh // strict
namespace HHRx;
use HHRx\Collection\KeyedContainerWrapper as KC;
use HHRx\Collection\VectorW;
use HHRx\Collection\KeyedProducer;
use HHRx\Collection\AsyncKeyedIteratorWrapper;
use HHRx\Collection\AsyncKeyedContainerWrapper as AsyncKC;
use HHRx\Collection\EmptyIterable;
<<__ConsistentConstruct>>
class KeyedStream<+Tk, +T> {
	private Vector<(function(T): Awaitable<void>)> $subscribers = Vector{};
	private Vector<(function(): Awaitable<void>)> $end_subscribers = Vector{};
	public function __construct(private KeyedProducer<Tk, T> $producer, private StreamFactory $factory) {}
	public async function run(): Awaitable<void> {
		foreach($this->producer await as $next) {
			await \HH\Asio\v($this->subscribers->map(((function(T): Awaitable<void>) $handler) ==> $handler($next))); // event subscriptions
		}
		await \HH\Asio\v($this->end_subscribers->map(((function(): Awaitable<void>) $handler) ==> $handler())); // end subscriptions
	}
	// public async function get_total_awaitable(): Awaitable<void> {
	// 	foreach($this->producer await as $_) {}
	// }
	public function get_producer(): KeyedProducer<Tk, T> {
		return $this->producer;
	}
	public function subscribe((function(T): Awaitable<void>) $incoming): void {
		$this->subscribers->add($incoming);
	}
	public function onEnd((function(): Awaitable<void>) $incoming): void {
		$this->end_subscribers->add($incoming);
	}
	public function keyed_transform<Tx super Tk, Tv super T>((function(Tk, T): Pair<Tx, Tv>) $f): KeyedStream<Tx, Tv> {
		return $this->factory->make(async {
			$producer = clone $this->get_producer();
			foreach($producer await as $k => $v) {
				// echo 'TRANSFORM';
				// var_dump($producer->get_stash());
				// var_dump($k);
				list($k, $v) = $f($k, $v);
				yield $k => $v;
			}
		});
	}
	// public function merge_with<Tx super Tk, Tr super T>(KeyedStream<Tx, Tr> $incoming): KeyedStream<Tx, Tr> {
	// 	return self::merge(Vector{ $this, $incoming });
	// }
	// public function concat<Tx super Tk, Tr super T>(KeyedStream<Tx, Tr> $incoming): KeyedStream<Tx, Tr> {
	// 	return new static(new AsyncKeyedIteratorWrapper(async { 
	// 		foreach($this->get_producer() await as $k => $v) yield $k => $v;
	// 		foreach($incoming->get_producer() await as $k => $v) yield $k => $v;
	// 	})); // consider self instead of static
	// }
	// public function filter((function(T): bool) $f): KeyedStream<Tk, T> {
	// 	return new self(new AsyncKeyedIteratorWrapper(async {
	// 		foreach($this->producer await as $k => $v)
	// 			if($f($v))
	// 				yield $k => $v;
	// 	}));
	// }
	public function end_with(KeyedStream<mixed, mixed> $incoming): void {
		$incoming->onEnd(async () ==> {
			$this->producer = new KeyedProducer(new \HHRx\Collection\EmptyKeyedIterator());
		});
	}
	public function end_on(Awaitable<mixed> $bound): void {
		$this->end_with($this->factory->make(async { 
			$resolved_bound = await $bound;
			yield $resolved_bound;
		}));
	}
	// An empty method doesn't make sense: for classes that use KeyedStream, make this KeyedStream nullable, null representing an empty stream
	// public static function empty(): KeyedStream<Tk, T> {
	// 	return new static(async{ 
	// 		while(true) {}
	// 	}); // consider self rather than static
	// }
}