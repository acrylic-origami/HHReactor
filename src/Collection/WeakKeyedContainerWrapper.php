<?hh // strict
namespace HHRx\Collection;
type WeakKeyedContainerWrapper<+Tk, +Tv> = KeyedContainerWrapper<Tk, Tv, KeyedContainer<Tk, Tv>>;