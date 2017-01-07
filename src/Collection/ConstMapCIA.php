<?hh // strict
namespace HHRx\Collection;
class ConstMapCIA<Tk as arraykey, +Tv, +TCollection as \ConstMap<Tk, Tv>, +TKeyUnderlying as \ConstVector<Tk>, +TKeyWrapperKeyWrapper as ConstVectorKeys, +TKeyWrapper as ConstVectorCIA<Tk, TKeyUnderlying, TKeyWrapperKeyWrapper>> extends IterableConstIndexAccess<Tk, Tv, TCollection, TKeyWrapper> {
	public function __construct(TCollection $collection, TKeyWrapper $keys) {
		parent::__construct($collection, $keys);
	}
}