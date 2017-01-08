<?hh // strict
namespace HHRx\Collection;
class VectorW<Tv> extends MutableKeyedContainerWrapper<int, Tv, Vector<Tv>> {
	use FulfillVectorW<Tv>;
}