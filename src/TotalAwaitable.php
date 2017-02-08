<?hh // strict
namespace HHReactor;
use HHReactor\Collection\LinkedList;
use HHReactor\Collection\Haltable;
// type EventHandler = (function((function(): Awaitable<void>)): void);
class TotalAwaitable implements Awaitable<mixed> {
	private Vector<Awaitable<mixed>> $subawaitables;
	private Awaitable<void> $total_awaitable;
	private Haltable<void> $partial;
	public function __construct(?Vector<Awaitable<mixed>> $initial = null) {
		$this->subawaitables = $initial ?? Vector{ \HH\Asio\later() };;
		$this->partial = new Haltable($this->await_current());
		$this->total_awaitable = async {
			for(; !$this->await_current()->getWaitHandle()->isFinished(); $this->partial = new Haltable($this->await_current())) {
				await $this->partial;
			}
		};
	}
	private function await_current(): Awaitable<void> {
		// return AwaitAllWaitHandle::fromVector($this->subhandles); // [OBSOLETE] AwaitAllWaitHandle smothers exceptions (it spits out a null instead of re-throwing) :(
		return async {
			await \HH\Asio\v($this->subawaitables);
		};
	}
	public async function add(Awaitable<void> $incoming): Awaitable<void> {
		$this->subawaitables->add($incoming);
		if(!$this->partial->getWaitHandle()->isFinished())
			await $this->partial->halt(); // reset the internal wait handle so that the new element is await-ed immediately
	}
	// public function add_stream<Tv>(Stream<Tv> $incoming): void {
	// 	$this->add($incoming->run());
	// }
	public function getWaitHandle(): WaitHandle<void> {
		return $this->total_awaitable->getWaitHandle();
	}
}