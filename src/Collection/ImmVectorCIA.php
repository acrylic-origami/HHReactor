<?hh // strict
namespace HHRx\Collection;
class ImmVectorCIA<+Tv> extends ConstVectorCIA<Tv, ImmVector<Tv>, ConstVectorKeys> {
	public function __construct(ImmVector<Tv> $collection = ImmVector{}) {
		parent::__construct($collection, ConstVectorKeys::class);
	}
}