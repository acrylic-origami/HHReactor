<?hh // strict
namespace HHReactor\Collection;
type ExactKeyedContainerWrapper<+Tk, +Tv> = KeyedContainerWrapper<Tk, Tv, KeyedContainer<Tk, Tv>>;