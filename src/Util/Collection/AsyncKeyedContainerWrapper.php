<?hh // strict
namespace HHRx\Util\Collection;
use HHRx\Util\Collection\KeyedContainerWrapper as KC;
class AsyncKeyedContainerWrapper<+Tk, +Tv> extends KC<Tk, Awaitable<Tv>> {
	public async function KCm(): Awaitable<KC<Tk, Tv>> {
		$M = Map{};
		$units = $this->get_units();
		if(!is_null($units)) {
			foreach($units as $k => $unit) {
				$M[$k] = await $unit;
			}
			return new KC($M);
		}
		else
			return new KC();
	}
	
	public async function KCmm<Tx>((function(Pair<Tk, Awaitable<Tv>>): Awaitable<Tx>) $f): Awaitable<KeyedContainerWrapper<Tk, Tx>> {
		$M = Map{};
		$units = $this->get_units();
		if(!is_null($units)) {
			foreach($units as $k => $unit) {
				$M[$k] = await $f(Pair{$k, $unit});
			}
			return new KC($M);
		}
		else
			return new KC();
	}
	
	public function async_map<Tu>((function(Awaitable<Tv>): Awaitable<Tu>) $fn): AsyncKeyedContainerWrapper<Tk, Tu> {
		// needed because Awaitable<Tu> won't fly with KeyedContainerWrapper::map
		$M = Map{};
		$units = $this->get_units();
		if(!is_null($units))
			foreach($units as $k=>$unit)
				$M[$k] = $fn($unit);
		return new static($M);
	}
}