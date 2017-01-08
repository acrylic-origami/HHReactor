<?hh // strict
namespace HHRx\Collection;
class AsyncMapW<Tk, Tv> extends AsyncMutableKeyedContainerWrapper<Tk, Tv, Map<Tk, Awaitable<Tv>>> {
	use FulfillMapW<Tk, Awaitable<Tv>>;
	<<__Override>>
	public async function m(): Awaitable<MapW<Tk, Tv>> {
		$ret = new MapW();
		foreach($this->get_units() as $k => $unit) {
			$ret->set($k, await $unit);
		}
		return $ret;
	}
}