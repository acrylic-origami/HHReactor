<?hh // strict
namespace HHReactor\Test;
use HHReactor\Producer;
use HHReactor\Appender;
class LifecycleTestProducer<+T> extends Producer<T> {
	public function __construct(
		Vector<(function(Appender<T>): AsyncIterator<T>)> $generator_factories,
		private (function(): void) $on_attach,
		private (function(): void) $on_detach,
		private (function(): void) $on_total_detach
	) {
		parent::__construct($generator_factories);
	}
	
	public static function _create(
		AsyncIterator<T> $incoming,
		(function(): void) $on_attach,
		(function(): void) $on_detach,
		(function(): void) $on_total_detach
	): LifecycleTestProducer<T> {
		return new self(Vector{ ($_) ==> $incoming }, $on_attach, $on_detach, $on_total_detach);
	}
	
	<<__Override>>
	public function detach(): void {
		parent::detach();
		$on_detach = $this->on_detach;
		$on_detach();
		if(!$this->some_running->get()->get()) {
			$on_total_detach = $this->on_total_detach;
			$on_total_detach();
		}
	}
	
	<<__Override>>
	protected async function _attach(): Awaitable<void> {
		await parent::_attach();
		$on_attach = $this->on_attach;
		$on_attach();
	}
}