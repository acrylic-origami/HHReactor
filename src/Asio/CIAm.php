<?hh // strict
namespace HHRx\Asio;
use HHRx\Collection\MapIA;
use HHRx\Collection\ConstMapCIA;
use HHRx\Collection\ExactConstMapCIA;
use HHRx\Collection\ConstVectorCIA;
use HHRx\Collection\ConstVectorKeys;
async function CIAm<Tk as arraykey, Tv>(ExactConstMapCIA<Tk, Awaitable<Tv>> $collection): Awaitable<ExactConstMapCIA<Tk, Tv>> {
	$M = Map{};
	foreach($collection as $k => $unit)
		$M[$k] = await $unit;
	return new ConstMapCIA($M, $collection->keys());
}