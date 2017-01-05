<?hh // strict
type EventHandler = (function((function(): Awaitable<void>)): void);
class TotalAwaitable {
	private ConditionWaitHandle<void> $_total_awaitable;
	private Vector<Awaitable<void>> $subawaitables;
	public function __construct(Awaitable<void> $initial) {
		$this->subawaitables = Vector{ $initial }; // requires at least one `Awaitable` that won't resolve right away
		$this->_total_awaitable = ConditionWaitHandle::create(async{
			for(; $this->subawaitables->valid(); $this->subawaitables->next()) {
				await $this->subawaitables->current();
			}
		});
	}
	public function add(Awaitable<void> $incoming): void {
		$this->subawaitables->add($incoming);
	}
	public function add_stream<Tk, Tv>(HHRx\KeyedStream<Tk, Tv> $incoming): void {
		$this->subawaitables->add($incoming->run());
	}
	public function get_awaitable(): Awaitable<void> {
		return $this->_total_awaitable;
	}
}