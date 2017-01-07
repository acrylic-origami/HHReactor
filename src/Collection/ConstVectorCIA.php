<?hh // strict
namespace HHRx\Collection;
class ConstVectorCIA<+Tv, +TCollection as \ConstVector<Tv>, +TKeyWrapper as ConstVectorKeys> extends IterableConstIndexAccess<int, Tv, TCollection, TKeyWrapper> implements ConsecutiveIterableConstIndexAccess<Tv> {
	public function __construct(TCollection $collection, classname<TKeyWrapper> $key_wrapper) {
		parent::__construct($collection, new $key_wrapper($collection->count()));
	}
	public function count(): int {
		return $this->keys()->count();
	}
}