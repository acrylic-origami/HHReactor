<?hh // strict
namespace HHRx;
use HHRx\Collection\KeyedProducer;
use HHRx\Collection\EmptyIterable;
<<__ConsistentConstruct>>
class KeyedStream<+Tk, +T> {
	private Vector<(function(T): Awaitable<void>)> $subscribers = Vector{};
	private Vector<(function(): Awaitable<void>)> $end_subscribers = Vector{};
	public function __construct(private KeyedProducer<Tk, T> $producer, private StreamFactory $factory) {}
	public async function run(): Awaitable<void> {
		foreach($this->producer await as $next) {
			$v = \HH\Asio\v($this->subscribers->map(((function(T): Awaitable<void>) $handler) ==> $handler($next)));
			await $v; // event subscriptions
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
	public function keyed_map<Tv>((function(Tk, T): Tv) $f): KeyedStream<Tk, Tv> {
		return $this->factory->make(async {
			$producer = clone $this->get_producer();
			foreach($producer await as $k => $v) {
				// echo 'TRANSFORM';
				// var_dump($producer->get_stash());
				// var_dump($k);
				$mapped_v = $f($k, $v);
				yield $k => $mapped_v;
			}
		});
	}
	public function map<Tv>((function(T): Tv) $f): KeyedStream<Tk, Tv> {
		return $this->keyed_map((Tk $k, T $v) ==> $f($v));
	}
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