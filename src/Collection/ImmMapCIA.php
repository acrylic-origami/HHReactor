<?hh // strict
namespace HHRx\Collection;
class ImmMapCIA<Tk as arraykey, +Tv> extends IterableConstIndexAccess<Tk, Tv, ImmMap<Tk, Tv>> {
	public function __construct(ImmMap<Tk, Tv> $collection = ImmMap{}) {
		parent::__construct($collection, \Vector::fromKeysOf($collection));
	}
}