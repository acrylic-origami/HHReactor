<?hh // strict
namespace HHRx\Collection;
class ConstMapCIA<Tk as arraykey, +Tv> extends IterableConstIndexAccess<Tk, Tv, \ConstMap<Tk, Tv>> {
	public function __construct(\ConstMap<Tk, Tv> $collection = Map{}) {
		parent::__construct($collection, \Vector::fromKeysOf($collection));
	}
}