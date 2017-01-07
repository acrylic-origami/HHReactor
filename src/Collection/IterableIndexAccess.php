<?hh // strict
namespace HHRx\Collection;
class IterableIndexAccess<Tk as arraykey, Tv, +TCollection as \IndexAccess<Tk, Tv>, +TKeyWrapper as ConsecutiveIterableIndexAccess<Tk>> extends IterableConstIndexAccess<Tk, Tv, TCollection, TKeyWrapper> implements \IndexAccess<Tk, Tv> {
	use IterableIndexAccessMutators<Tk, Tv, TCollection, TKeyWrapper>;
}