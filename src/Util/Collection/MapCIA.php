<?hh // strict
namespace HHRx\Util\Collection;
class MapIA<Tk as arraykey, Tv> extends IterableIndexAccess<Tk, Tv, Map<Tk, Tv>> {
	public function __construct(Map<Tk, Tv> $collection = Map{}) {
		parent::__construct($collection, \Set::fromKeysOf($collection));
	}
}
class ImmMapCIA<Tk as arraykey, +Tv> extends IterableConstIndexAccess<Tk, Tv, ImmMap<Tk, Tv>> {
	public function __construct(ImmMap<Tk, Tv> $collection = ImmMap{}) {
		parent::__construct($collection, \Set::fromKeysOf($collection));
	}
}
class ConstMapCIA<Tk as arraykey, +Tv> extends IterableConstIndexAccess<Tk, Tv, \ConstMap<Tk, Tv>> {
	public function __construct(\ConstMap<Tk, Tv> $collection = Map{}) {
		parent::__construct($collection, \Set::fromKeysOf($collection));
	}
}
class VectorIA<Tv> extends IterableIndexAccess<int, Tv, Vector<Tv>> {
	public function __construct(Vector<Tv> $collection = Vector{}) {
		parent::__construct($collection, \Set::fromKeysOf($collection));
	}
}
class ImmVectorCIA<+Tv> extends IterableConstIndexAccess<int, Tv, ImmVector<Tv>> {
	public function __construct(ImmVector<Tv> $collection = ImmVector{}) {
		parent::__construct($collection, \Set::fromKeysOf($collection));
	}
}