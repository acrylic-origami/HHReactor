<?hh // strict
namespace HHRx;
use HHRx\Collection\LinkedList;
// type EventHandler = (function((function(): Awaitable<void>)): void);
class TotalAwaitable {
	private Awaitable<void> $_total_awaitable;
	private LinkedList<Awaitable<void>> $subawaitables;
	public function __construct(Awaitable<void> $initial) {
		$this->subawaitables = new LinkedList(Vector{ $initial }); // requires at least one `Awaitable` that won't resolve right away
		$this->_total_awaitable = (async () ==> {
			// note: cannot use \HH\Asio\v because a longer awaitable could be added. Also, can't use Vector because Vector complains that it's being changed during iteration
			foreach($this->subawaitables as $subawaitable)
				await $subawaitable;
		})();
	}
	public function add(Awaitable<void> $incoming): void {
		$this->subawaitables->add($incoming);
	}
	public function add_stream<Tk, Tv>(KeyedStream<Tk, Tv> $incoming): void {
		$this->subawaitables->add($incoming->run());
	}
	public function get_awaitable(): Awaitable<void> {
		return $this->_total_awaitable;
	}
	public function get_static_awaitable(): Awaitable<Vector<void>> {
		return \HH\Asio\v($this->subawaitables);
	}
}