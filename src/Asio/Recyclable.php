<?hh // strict
namespace HHReactor\Asio;
// type EventHandler = (function((function(): Awaitable<void>)): void);
<<__ConsistentConstruct>>
abstract class Recyclable<T> implements Awaitable<T> {
	protected Awaitable<T> $total_awaitable;
	protected Haltable<T> $partial;
	public function __construct(Awaitable<T> $initial) {
		$this->partial = new Haltable($initial);
		$this->_reset();
	}
	private function _reset(): void {
		/* HH_IGNORE_ERROR[4110] $v['result'] is always type T (which may or may not be nullable) because !_halted */
		$this->total_awaitable = async {
			do {
				invariant(!\HH\Asio\has_finished($this->partial), 'Implementation error: underlying awaitable not replaced properly/quickly enough. Aborting to prevent infinite loop.');
				$v = await $this->partial;
			}
			while(!$v['_halted']);
			return $v['result'];
		};
	}
	protected function _soft_replace(Awaitable<T> $incoming): void {
		if(!$this->partial->is_halted())
			$this->partial->soft_halt();
		
		$this->partial = new Haltable($incoming);
		
		if(\HH\Asio\has_finished($this->total_awaitable))
			$this->_reset();
	}
	protected async function _replace(Awaitable<T> $incoming): Awaitable<void> {
		$this->_soft_replace($incoming);
		await \HH\Asio\later();
	}
	// public function add_stream<Tv>(Stream<Tv> $incoming): void {
	// 	$this->add($incoming->run());
	// }
	public function getWaitHandle(): WaitHandle<T> {
		return $this->total_awaitable->getWaitHandle();
	}
}