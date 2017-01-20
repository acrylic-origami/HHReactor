<?hh // strict
namespace HHRx;
use HHRx\Collection\LinkedList;
use HHRx\Collection\Haltable;
// type EventHandler = (function((function(): Awaitable<void>)): void);
class TotalAwaitable {
	private Vector<WaitHandle<mixed>> $subhandles;
	private Awaitable<void> $total_awaitable;
	private Haltable<void> $partial;
	public function __construct(?Vector<Awaitable<mixed>> $initial = null) {
		$subawaitables = $initial ?? Vector{ \HH\Asio\later() };
		$this->subhandles = $subawaitables->map((Awaitable<mixed> $awaitable) ==> $awaitable->getWaitHandle());
		$this->partial = new Haltable($this->await_all());
		$this->total_awaitable = async {
			for(; !$this->await_all()->isFinished(); $this->partial = new Haltable($this->await_all()))
				await $this->partial;
		};
	}
	private function await_all(): WaitHandle<void> {
		return AwaitAllWaitHandle::fromVector($this->subhandles);
	}
	public function add(Awaitable<void> $incoming): void {
		$this->subhandles->add($incoming->getWaitHandle());
		if(!is_null($this->partial))
			$this->partial->soft_halt(); // reset the internal wait handle so that the new element is await-ed immediately
	}
	public function add_stream<Tv>(Stream<Tv> $incoming): void {
		$this->subhandles->add($incoming->run()->getWaitHandle());
		if(!is_null($this->partial))
			$this->partial->soft_halt(); // reset the internal wait handle so that the new element is await-ed immediately
	}
	public function get_awaitable(): Awaitable<void> {
		return $this->total_awaitable;
	}
}