<?hh // strict
namespace HHRx\Collection;
class AsyncMutableKeyedContainerWrapper<Tk, Tv, +TCollection as \MutableKeyedContainer<Tk, Awaitable<Tv>>> extends AsyncKeyedContainerWrapper<Tk, Tv, TCollection> {
	use FulfillMutableKeyedContainerWrapper<Tk, Awaitable<Tv>, TCollection>;
	<<__Override>>
	public async function m(): Awaitable<MutableKeyedContainerWrapper<Tk, Tv, \MutableKeyedContainer<Tk, Tv>>> {
		$ret = new MapW();
		foreach($this->get_units() as $k => $unit) {
			$ret->set($k, await $unit);
		}
		return $ret;
	}
}