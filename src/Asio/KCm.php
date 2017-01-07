<?hh // strict
namespace HHRx\Asio;
use HHRx\Collection\KeyedContainerWrapper as KC;
async function KCm<Tk, Tv>(KC<Tk, Awaitable<Tv>> $incoming): Awaitable<KC<Tk, Tv>> {
	$M = Map{};
	foreach($incoming as $k => $v)
		$M[$k] = await $v;
	return new KC($M);
}