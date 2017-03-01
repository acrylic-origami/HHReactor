<?hh // strict
namespace HHReactor\Collection;
use HHReactor\Asio\ExtendableLifetime;
/**
 * Allow any scope to add emitters at any time during the lifetime of the instance.
 * 
 * (Caveat: possible to make covariant and restrict to only the original emitters, which is the only scope it is used in all functionality in HHReactor so far. The only downside is the cumber of the argument signatures for the functions that must both emit and add emitters. Much easier to just hand the whole `this` type to them.)
 */
class Subject<T> implements AsyncIterator<T> {
	protected ConditionWaitHandle<mixed> $bell;
	protected ExtendableLifetime $total_awaitable;
	protected Queue<T> $lag;
	public function __construct(
		Vector<(function(this): Awaitable<mixed>)> $emitters,
		(function(Vector<Awaitable<mixed>>): Awaitable<mixed>) $reducer = fun('\HH\Asio\v'),
		Vector<Awaitable<mixed>> $sidechain = Vector{}
	) {
		$this->lag = new Queue();
		$this->total_awaitable = new ExtendableLifetime(async {
			await \HH\Asio\later();
			await $reducer(
				$emitters->map(($emitter) ==> $emitter($this))
                     ->concat($sidechain)
			);
		});
		$this->bell = ConditionWaitHandle::create(\HHReactor\Asio\voidify($this->total_awaitable->getWaitHandle()));
	}
	public function __clone(): void {
		$this->lag = clone $this->lag;
	}
	public function attach((function(this): Awaitable<void>) $emitter): void {
		$this->total_awaitable->soft_extend($emitter($this));
	}
	public function sidechain(Awaitable<void> $incoming): void {
		$this->total_awaitable->soft_extend($incoming);
	}
	public function emit(T $v): void {
		// contrast public `emit` with private `EmitIterator::_emit`
		if(!$this->bell->isFinished())
			$this->bell->succeed(null);
		$this->lag->add($v);
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