<?hh // strict
namespace HHReactor\Collection;
use HHReactor\Asio\ExtendableLifetime;
class EmitIterator<+T> implements AsyncIterator<T> {
	private ConditionWaitHandle<mixed> $bell;
	private ExtendableLifetime $total_awaitable;
	private Queue<T> $lag;
	public function __construct(
		Vector<(function(EmitTrigger<T>): Awaitable<mixed>)> $emitters,
		(function(Vector<Awaitable<mixed>>): Awaitable<mixed>) $reducer = fun('\HH\Asio\v'),
		Vector<Awaitable<mixed>> $sidechain = Vector{}
	) {
		$trigger = (T $v) ==> $this->_emit($v); // publicize `_emit` trigger for emitters
		$this->total_awaitable = new ExtendableLifetime(async {
			await \HH\Asio\later();
			await $reducer(
				$emitters->map(($emitter) ==> $emitter($trigger))
                     ->concat($sidechain)
			);
		});
		$this->bell = ConditionWaitHandle::create($this->total_awaitable->getWaitHandle());
		$this->lag = new Queue();
	}
	public function __clone(): void {
		$this->lag = clone $this->lag;
	}
	private function _emit(T $v): void {
		if(!$this->bell->isFinished())
			$this->bell->succeed(null);
		$this->lag->add($v);
	}
	public function sidechain(Awaitable<void> $incoming): void {
		$this->total_awaitable->soft_extend($incoming);
	}
	public async function next(): Awaitable<?(mixed, T)> {
		if($this->lag->is_empty()) {
			if($this->bell->isFinished())
				// idempotent reset
				if(\HH\Asio\has_finished($this->total_awaitable))
					$this->bell = ConditionWaitHandle::create($this->total_awaitable->getWaitHandle());
				else
					return null;
			
			await $this->bell;
		}
		return tuple(null, $this->lag->shift());
	}
}