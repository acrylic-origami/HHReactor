<?hh // strict
namespace HHRx\Collection;
class EmptyVectorFactory<Tv> implements EmptyKeyedContainerFactory<int, Tv, ?Vector<Tv>> {
	public function __construct() {}
	public function make_container(): ?Vector<Tv> {
		return Vector{};
	}
}