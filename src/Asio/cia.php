<?hh // strict
namespace HHRx\Asio;
use HHRx\Collection\IterableConstIndexAccess as IterableCIA;
async function cia<Tk as arraykey, Tv, TCollection as ?\ConstIndexAccess<Tk, Awaitable<Tv>>, TCollectionAfter as ?\ConstIndexAccess<Tk, Tv>>(IterableCIA<Tk, Awaitable<Tv>, TCollection> $collection): Awaitable<IterableCIA<Tk, Tv, TCollectionAfter>> {
	$M = Map{};
	foreach($collection as $k => $unit) {
		$M[$k] = await $unit;
	}
	$wrapper = $collection->get_wrapper();
	return new IterableCIA($M, $collection->get_keys());
}