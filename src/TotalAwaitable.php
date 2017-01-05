<?hh // strict
namespace HHRx;
use HHRx\Util\Collection\VectorIA;
// type EventHandler = (function((function(): Awaitable<void>)): void);
class TotalAwaitable {
	private ConditionWaitHandle<void> $_total_awaitable;
	private VectorIA<Awaitable<void>> $subawaitables;
	public function __construct(Awaitable<void> $initial) {
		$this->subawaitables = new VectorIA(Vector{ $initial }); // requires at least one `Awaitable` that won't resolve right away
		$this->_total_awaitable = ConditionWaitHandle::create((async{
			foreach($this->subawaitables as $subawaitable) {
				await $subawaitable;
			}
		})->getWaitHandle());
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
}