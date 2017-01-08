<?hh // strict
namespace HHRx\Collection;
type WeakMutableKeyedContainerWrapper<Tk, Tv> = MutableKeyedContainerWrapper<Tk, Tv, \MutableKeyedContainer<Tk, Tv>>;