<?hh // strict
namespace HHRx;
use HHRx\Collection\KeyedContainerWrapper as KC;
use HHRx\Collection\VectorW;
use HHRx\Collection\AsyncKeyedContainerWrapper as AsyncKC;
use HHRx\Collection\EmptyIterable;
<<__ConsistentConstruct>>
class KeyedStream<+Tk, +T> {
	private Vector<(function(T): Awaitable<void>)> $subscribers = Vector{};
	private Vector<(function(): Awaitable<void>)> $end_subscribers = Vector{};
	public function __construct(private AsyncKeyedIterator<Tk, T> $producer) {}
	public async function run(): Awaitable<void> {
		foreach($this->producer await as $next)
			await \HH\Asio\v($this->subscribers->map(((function(T): Awaitable<void>) $handler) ==> $handler($next))); // event subscriptions
		await \HH\Asio\v($this->end_subscribers->map(((function(): Awaitable<void>) $handler) ==> $handler())); // end subscriptions
	}
	public async function get_total_awaitable(): Awaitable<void> {
		foreach($this->producer await as $_) {}
	}
	public function get_producer(): AsyncKeyedIterator<Tk, T> {
		return $this->producer;
	}
	public function subscribe((function(T): Awaitable<void>) $incoming): void {
		$this->subscribers->add($incoming);
	}
	public function onEnd((function(): Awaitable<void>) $incoming): void {
		$this->end_subscribers->add($incoming);
	}
	public function merge_with<Tx super Tk, Tr super T>(KeyedStream<Tx, Tr> $incoming): KeyedStream<Tx, Tr> {
		return self::merge(Vector{ $this, $incoming });
	}
	public function concat<Tx super Tk, Tr super T>(KeyedStream<Tx, Tr> $incoming): KeyedStream<Tx, Tr> {
		return new static(async { 
			foreach($this->get_producer() await as $k => $v) yield $k => $v;
			foreach($incoming->get_producer() await as $k => $v) yield $k => $v;
		}); // consider self instead of static
	}
	public function filter((function(T): bool) $f): KeyedStream<Tk, T> {
		return new self(async {
			foreach($this->producer await as $k => $v)
				if($f($v))
					yield $k => $v;
		});
	}
	public function end_with(KeyedStream<mixed, mixed> $incoming): void {
		$incoming->onEnd(async () ==> {
			$this->producer = new \HHRx\Collection\EmptyKeyedIterator();
		});
	}
	public function end_on(Awaitable<mixed> $bound): void {
		$this->end_with(new KeyedStream(async { 
			$resolved_bound = await $bound;
			yield $resolved_bound;
		}));
	}
	public static function merge<Tx, Tr>(KeyedContainer<mixed, KeyedStream<Tx, Tr>> $incoming): KeyedStream<Tx, Tr> {
		// sacrificing `map` here because KeyedContainerWrapper isn't instantiable
		$producers = Vector{};
		foreach($incoming as $substream) {
			$producers->add($substream->get_producer());
		}
		return new static(new AsyncKeyedIteratorPoll(new VectorW($producers))); // consider self rather than static
	}
	public static function just<Tx, Tv>(Awaitable<Tv> $incoming, ?Tx $key = null): KeyedStream<?Tx, Tv> {
		return new static(async {
			$resolved_incoming = await $incoming;
			yield $key => $resolved_incoming;
		}); // consider self rather than static
	}
	public static function from<Tx, Tv>(KeyedIterable<Tx, Awaitable<Tv>> $incoming): KeyedStream<Tx, Tv> {
		return new static(async { 
			foreach($incoming as $k => $awaitable) {
				$resolved_awaitable = await $awaitable;
				yield $k => $resolved_awaitable;
			}
		}); // consider self rather than static
	}
	// An empty method doesn't make sense: for classes that use KeyedStream, make this KeyedStream nullable, null representing an empty stream
	// public static function empty(): KeyedStream<Tk, T> {
	// 	return new static(async{ 
	// 		while(true) {}
	// 	}); // consider self rather than static
	// }
}