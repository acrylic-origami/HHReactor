<?hh // strict
namespace HHRx\Collection;
abstract class MutableKeyedContainerWrapper<Tk, Tv, +TCollection as \MutableKeyedContainer<Tk, Tv>> extends KeyedContainerWrapper<Tk, Tv, TCollection> implements \MutableKeyedContainer<Tk, Tv> {
	use FulfillMutableKeyedContainerWrapper<Tk, Tv, TCollection>;
}