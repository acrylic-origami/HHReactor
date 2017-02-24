<?hh // strict
namespace HHReactor\Asio;
use HHReactor\Collection\Producer;
class ExtendableLifetime extends Recyclable<void> {
	private Vector<Awaitable<void>> $subawaitables = Vector{};
	public async function extend(Awaitable<void> $incoming): Awaitable<void> {
		$this->soft_extend($incoming);
		await \HH\Asio\later();
	}
	public function soft_extend(Awaitable<void> $incoming): void {
		$this->_soft_replace(
			Producer::from_nonblocking($this->subawaitables->add($incoming))
				     ->get_lifetime()
   	);
	}
}