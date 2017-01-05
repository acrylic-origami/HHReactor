<?hh // strict
namespace HHRx\Util\Collection;
class ImmVectorCIA<+Tv> extends IterableConstIndexAccess<int, Tv, ImmVector<Tv>> {
	public function __construct(ImmVector<Tv> $collection = ImmVector{}) {
		parent::__construct($collection, \Vector::fromKeysOf($collection));
	}
}