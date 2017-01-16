<?hh // strict
namespace HHRx;
use HHRx\Collection\Producer;
use HHRx\Collection\EmptyIterable;
<<__ConsistentConstruct>>
class Stream<+T> {
	private Vector<(function(T): Awaitable<void>)> $subscribers = Vector{};
	private Vector<(function(): Awaitable<void>)> $end_subscribers = Vector{};
	public function __construct(private Producer<T> $producer, private StreamFactory $factory) {}
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
	public function get_producer(): Producer<T> {
		return $this->producer;
	}
	public function subscribe((function(T): Awaitable<void>) $incoming): void {
		$this->subscribers->add($incoming);
	}
	public function onEnd((function(): Awaitable<void>) $incoming): void {
		$this->end_subscribers->add($incoming);
	}
	public function map<Tv>((function(T): Tv) $f): Stream<Tv> {
		return $this->factory->make(async {
			$producer = clone $this->get_producer();
			foreach($producer await as $v) {
				$mapped_v = $f($v);
				yield $mapped_v;
			}
		});
	}
	public function buffer(Producer<mixed> $signal): Stream<\ConstVector<T>> {
		return $this->factory->make(async {
			$producer = clone $this->producer;
			foreach($signal await as $_)
				yield new Vector($producer->fast_forward());
		});
	}
	// public function collapse(): Awaitable<KeyedContainer<Tk, T>> {
	// 	$M = Map{};
	// 	$this->subscribe(inst_meth($M, 'set'));
		
	// }
	public function end_with(Stream<mixed> $incoming): void {
		$incoming->onEnd(inst_meth($this->producer, 'halt')); // halt with null to signal iterator end);
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