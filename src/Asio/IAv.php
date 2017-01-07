<?hh // strict
namespace HHRx\Asio;
use HHRx\Collection\ConstMapCIA;
use HHRx\Collection\ConstVectorCIA;
use HHRx\Collection\MapIA;
use HHRx\Collection\VectorIA;
async function IAv<Tv>(VectorIA<Awaitable<Tv>> $collection): Awaitable<VectorIA<Tv>> {
	$V = Vector{};
	foreach($collection as $unit) {
		$V->add(await $unit);
	}
	return new VectorIA(new Vector($V));
}