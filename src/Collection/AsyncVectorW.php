<?hh // strict
namespace HHRx\Collection;
class AsyncVectorW<Tv> extends AsyncMutableKeyedContainerWrapper<int, Tv, Vector<Awaitable<Tv>>> {
	use FulfillVectorW<Awaitable<Tv>>;
	<<__Override>>
	public async function m(): Awaitable<VectorW<Tv>> {
		$ret = new VectorW();
		foreach($this->get_units() as $k => $unit) {
			$ret->add(await $unit);
		}
		return $ret;
	}
}