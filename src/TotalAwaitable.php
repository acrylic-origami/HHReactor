<?hh // strict
namespace HHRx;
use HHRx\Collection\LinkedList;
use HHRx\Collection\Haltable;
// type EventHandler = (function((function(): Awaitable<void>)): void);
class TotalAwaitable implements Awaitable<mixed> {
	private Vector<Awaitable<mixed>> $subhandles;
	private Awaitable<void> $total_awaitable;
	private Haltable<void> $partial;
	public function __construct(?Vector<Awaitable<mixed>> $initial = null) {
		$subawaitables = $initial ?? Vector{ \HH\Asio\later() };
		$this->subhandles = $subawaitables->map((Awaitable<mixed> $awaitable) ==> $awaitable->getWaitHandle());
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
			await \HH\Asio\v($this->subhandles);
		};
	}
	public function add(Awaitable<void> $incoming): void {
		$this->subhandles->add($incoming);
		if(!$this->partial->getWaitHandle()->isFinished())
			$this->partial->soft_halt(); // reset the internal wait handle so that the new element is await-ed immediately
	}
	public function add_stream<Tv>(Stream<Tv> $incoming): void {
		$this->add($incoming->run());
	}
	public function getWaitHandle(): WaitHandle<mixed> {
		return $this->total_awaitable->getWaitHandle();
	}
}