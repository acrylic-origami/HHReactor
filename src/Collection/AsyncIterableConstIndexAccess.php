<?hh // strict
namespace HHRx\Collection;
use HHRx\Collection\IterableConstIndexAccess as IterableCIA;
class AsyncIterableConstIndexAccess<Tk, +Tv> extends IterableCIA<Tk, Awaitable<Tv>> {
	public async function KCm(): Awaitable<IterableCIA<Tk, Tv>> {
		$M = Map{};
		foreach($this as $k => $unit) {
			$M[$k] = await $unit;
		}
		return new IterableCIA($M);
	}
	
	public async function KCmm<Tx>((function(Tk, Awaitable<Tv>): Awaitable<Tx>) $f): Awaitable<IterableCIA<Tk, Tx>> {
		$M = Map{};
		foreach($this as $k => $unit) {
			$M[$k] = await $f(Pair{$k, $unit});
		}
		return new IterableCIA($M);
	}
	
	public function async_map<Tu>((function(Awaitable<Tv>): Awaitable<Tu>) $fn): AsyncKeyedContainerWrapper<Tk, Tu> {
		// needed because Awaitable<Tu> won't fly with KeyedContainerWrapper::map
		$M = Map{};
		foreach($this as $k=>$unit)
			$M[$k] = $fn($unit);
		return new static($M);
	}
	
	public function async_keyed_map<Tu>((function(Tk, Awaitable<Tv>): Awaitable<Tu>) $fn): AsyncKeyedContainerWrapper<Tk, Tu> {
		// needed because Awaitable<Tu> won't fly with KeyedContainerWrapper::map
		$M = Map{};
		foreach($this as $k=>$unit)
			$M[$k] = $fn($k, $unit);
		return new static($M);
	}
}