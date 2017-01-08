<?hh // strict
namespace HHRx\Collection;
class MapW<Tk, Tv> extends MutableKeyedContainerWrapper<Tk, Tv, Map<Tk, Tv>> {
	use FulfillMapW<Tk, Tv>;
}