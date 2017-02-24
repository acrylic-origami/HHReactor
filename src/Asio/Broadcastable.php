<?hh // strict
namespace HHReactor\Asio;
class Broadcastable<T> extends Recyclable<T> {
	public function __construct(private Awaitable<T> $lifetime) {
		parent::__construct($this->lifetime);
	}
	public async function broadcast(T $v): Awaitable<void> {
		await $this->_replace(async { return $v; });
		await $this->_replace($this->lifetime);
	}
}