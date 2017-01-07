<?hh // strict
namespace HHRx\Asio;
use HHRx\Collection\ConstMapCIA;
use HHRx\Collection\ConstVectorCIA;
use HHRx\Collection\MapIA;
use HHRx\Collection\VectorIA;
async function IAm<Tk as arraykey, Tv>(MapIA<Tk, Awaitable<Tv>> $collection): Awaitable<MapIA<Tk, Tv>> {
	$M = Map{};
	foreach($collection as $k => $unit) {
		$M[$k] = await $unit;
	}
	return new MapIA($M, $collection->keys()->clone());
}