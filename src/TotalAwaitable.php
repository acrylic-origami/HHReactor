<?hh // strict
namespace HHRx;
use HHRx\Collection\LinkedList;
// type EventHandler = (function((function(): Awaitable<void>)): void);
class TotalAwaitable {
	private Awaitable<void> $_total_awaitable;
	private LinkedList<Awaitable<mixed>> $subawaitables; // note: $subawaitables is append-only.
	public function __construct(?Iterable<Awaitable<mixed>> $initial = null) {
		$this->subawaitables = new LinkedList($initial ?? Vector{ \HH\Asio\later() });
		$this->_total_awaitable = async {
			// note: cannot use \HH\Asio\v because a longer awaitable could be added. Also, can't use Vector because Vector complains that it's being changed during iteration. +, marching the linked list with `shift` frees that memory
			while(!$this->subawaitables->is_empty())
				await $this->subawaitables->shift();
		};
	}
	public function add(Awaitable<void> $incoming): void {
		$this->subawaitables->add($incoming);
	}
	public function add_stream<Tv>(Stream<Tv> $incoming): void {
		$this->subawaitables->add($incoming->run());
	}
	public function get_awaitable(): Awaitable<void> {
		return $this->_total_awaitable;
	}
	// because MarchingLinkedList doesn't let us peek and make an 
	// public function get_static_awaitable(): Awaitable<Vector<void>> {
	// 	return \HH\Asio\v($this->subawaitables->getIterator());
	// }
}