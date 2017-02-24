<?hh // strict
namespace HHReactor\Asio;
use HHReactor\Collection\Producer;
class Extendable<T> extends Recyclable<\ConstVector<T>> {
	private Vector<Awaitable<T>> $subawaitables = Vector{};
	public async function extend(Awaitable<T> $incoming): Awaitable<void> {
		$this->soft_extend($incoming);
		await \HH\Asio\later();
	}
	public function soft_extend(Awaitable<T> $incoming): void {
		$this->_soft_replace(
			Producer::from_nonblocking($this->subawaitables->add($incoming))
				     ->collapse()
   	);
	}
}