<?hh // strict
namespace HHRx\Asio;
use HHRx\Collection\ConstMapCIA;
use HHRx\Collection\ConstVectorCIA;
use HHRx\Collection\ConstVectorKeys;
use HHRx\Collection\MapIA;
use HHRx\Collection\VectorIA;
async function CIAv<Tv>(ConstVectorCIA<Awaitable<Tv>, \ConstVector<Awaitable<Tv>>, ConstVectorKeys> $collection): Awaitable<ConstVectorCIA<Tv, \ConstVector<Tv>, ConstVectorKeys>> {
	$V = Vector{};
	foreach($collection as $k => $unit) {
		$V->add(await $unit);
	}
	return new VectorIA($V);
}