<?hh // strict
namespace HHReactor\Asio;
use HHReactor\Collection\Producer;
/**
 * Endpoint is at least as late as the last Awaitable added to it while still unresolved.
 */
class ExtendableLifetime extends Recyclable<mixed> {
	private Vector<Awaitable<mixed>> $subawaitables = Vector{};
	public function soft_extend(Awaitable<mixed> $incoming): void {
		$this->_soft_replace(
			\HH\Asio\v($this->subawaitables->add($incoming))
   	);
	}
}