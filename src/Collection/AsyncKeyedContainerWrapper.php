<?hh // strict
namespace HHRx\Collection;
abstract class AsyncKeyedContainerWrapper<Tk, +Tv, +TCollection as KeyedContainer<Tk, Awaitable<Tv>>> extends KeyedContainerWrapper<Tk, Awaitable<Tv>, TCollection> {
	public async function m(): Awaitable<KeyedContainerWrapper<Tk, Tv, KeyedContainer<Tk, Tv>>> {
		$ret = new MapW();
		foreach($this->get_units() as $k => $unit) {
			$ret->set($k, await $unit);
		}
		return $ret;
	}
}