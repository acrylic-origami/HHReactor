<?hh // strict
namespace HHReactor;
use HHReactor\Collection\Producer;
use HHReactor\Collection\EmptyIterable;
<<__ConsistentConstruct>>
class Stream<+T> {
	private Vector<(function(T): Awaitable<mixed>)> $subscribers = Vector{};
	private Vector<(function(): Awaitable<mixed>)> $end_subscribers = Vector{};
	public function __construct(private Producer<T> $producer, private StreamFactory $factory) {}
	public async function run(): Awaitable<void> {
		// try {
			foreach($this->producer await as $next) {
				$v = \HH\Asio\v($this->subscribers->map(((function(T): Awaitable<mixed>) $handler) ==> $handler($next)));
				await $v; // event subscriptions
			}
		// }
		// catch(\Exception $e) {
		// 	echo $e->getTraceAsString();
		// 	// throw $e;
		// }
		await \HH\Asio\v($this->end_subscribers->map(((function(): Awaitable<mixed>) $handler) ==> $handler())); // end subscriptions
	}
	// public async function get_total_awaitable(): Awaitable<void> {
	// 	foreach($this->producer await as $_) {}
	// }
	public function clone_producer(): Producer<T> {
		return clone $this->producer;
	}
	public function subscribe((function(T): Awaitable<mixed>) $incoming): void {
		$this->subscribers->add($incoming);
		// return () ==> {
		// 	$this->subscribers->removeKey()
		// };
	}
	public function onEnd((function(): Awaitable<mixed>) $incoming): void {
		$this->end_subscribers->add($incoming);
	}
	public function map<Tv>((function(T): Tv) $f): Stream<Tv> {
		return $this->factory->make(async {
			$producer = $this->clone_producer();
			foreach($producer await as $v) {
				$mapped_v = $f($v);
				yield $mapped_v;
			}
		});
	}
	public function buffer(Stream<mixed> $signal): Stream<\ConstVector<T>> {
		return $this->factory->make(async {
			$producer = $this->clone_producer();
			foreach($signal->clone_producer() await as $_)
				yield new Vector($producer->fast_forward());
		});
	}
	public async function await_end(): Awaitable<void> {
		$total_wait_handle = $this->factory->get_total_awaitable()->getWaitHandle();
		if(!$total_wait_handle->isFinished()) {
			$wait_handle = ConditionWaitHandle::create($total_wait_handle); // assume getWaitHandle doesn't freeze the total_awaitable to the current linked list of subawaitables (which doesn't even make sense to say)
			$this->onEnd(async () ==> {
				// this is an end handler of this stream, so this adds to the wait of total_awaitable.
				// The `later` call ensures that waiting the local $wait_handle below executes before the end handlers do => before total_awaitable.
				// Note that $wait_handle is local to this method, so this is the only possible resolver.
				$wait_handle->succeed(null);
				await \HH\Asio\later();
			});
			await $wait_handle;
		}
	}
	public async function collapse(): Awaitable<\ConstVector<T>> {
		$accumulator = Vector{};
		$this->subscribe(async (T $v) ==> { 
			$accumulator->add($v);
		});
		await $this->await_end();
		return $accumulator;
	}
	public function using(Stream<mixed> $incoming): void {
		$incoming->onEnd(inst_meth($this->producer, 'halt')); // halt with null to signal iterator end);
	}
	public function end_on(Awaitable<mixed> $bound): void {
		$this->using($this->factory->make(async { 
			$resolved_bound = await $bound;
			yield $resolved_bound;
		}));
	}
	// An empty method doesn't make sense: for classes that use KeyedStream, make this KeyedStream nullable, null representing an empty stream
}