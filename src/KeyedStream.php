<?hh // strict
namespace HHRx;
use HHRx\Util\Collection\KeyedContainerWrapper as KC;
use HHRx\Util\Collection\AsyncKeyedContainerWrapper as AsyncKC;
<<__ConsistentConstruct>>
class KeyedStream<+Tk, +T> {
	private Vector<(function(T): Awaitable<void>)> $subscribers = Vector{};
	public function __construct(private AsyncKeyedIterator<Tk, T> $producer) {}
	public async function run(): Awaitable<void> {
		foreach($this->producer await as $val) {
			await \HH\Asio\v($this->subscribers->map(((function(T): Awaitable<void>) $handler) ==> $handler($val)));
		}
	}
	protected function get_producer(): AsyncKeyedIterator<Tk, T> {
		return $this->producer;
	}
	public function subscribe((function(T): Awaitable<void>) $incoming): void {
		$this->subscribers->add($incoming);
	}
	public function merge<Tx super Tk, Tr super T>(KeyedStream<Tx, Tr> $incoming): KeyedStream<Tx, Tr> {
		return self::merge_all(Vector{ $this, $incoming });
	}
	public function concat<Tx super Tk, Tr super T>(KeyedStream<Tx, Tr> $incoming): KeyedStream<Tx, Tr> {
		return new static(async { 
			foreach($this->get_producer() await as $k => $v) yield $k => $v;
			foreach($incoming->get_producer() await as $k => $v) yield $k => $v;
		});
	}
	public static function merge_all<Tx, Tr>(KeyedContainer<arraykey, KeyedStream<Tx, Tr>> $incoming): KeyedStream<Tx, Tr> {
		$producers = (new KC($incoming))->map((KeyedStream<Tx, Tr> $stream) ==> $stream->get_producer())->get_units();
		invariant(!is_null($producers), 'Impossible condition or implementation error: argument KeyedContainer is not nullable, but is weakened by KC construction.');
		return new static(new AsyncKeyedIteratorPoll($producers));
	}
	public static function from_one<Tx, Tv>(Awaitable<Tv> $incoming, ?Tx $key = null): KeyedStream<?Tx, Tv> {
		return new static(async { yield $key => (await $incoming); });
	}
	public static function from<Tx, Tv>(KeyedIterable<Tx, Awaitable<Tv>> $incoming): KeyedStream<Tx, Tv> {
		return new static(async { foreach($incoming as $k => $awaitable) yield $k => await $awaitable; });
	}
}