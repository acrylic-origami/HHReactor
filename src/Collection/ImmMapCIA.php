<?hh // strict
namespace HHRx\Collection;
class ImmMapCIA<Tk as arraykey, +Tv> extends ConstMapCIA<Tk, Tv, ImmMap<Tk, Tv>, ImmVector<Tk>, ConstVectorKeys, ImmVectorCIA<Tk>> {
	public function __construct(ImmMap<Tk, Tv> $collection = ImmMap{}, ?ImmVectorCIA<Tk> $keys = null) {
		parent::__construct($collection, $keys ?? new ImmVectorCIA(ImmVector::fromKeysOf($collection)));
	}
}