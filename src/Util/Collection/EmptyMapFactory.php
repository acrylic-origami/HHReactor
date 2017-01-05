<?hh // strict
namespace HHRx\Util\Collection;
class EmptyMapFactory<Tk as arraykey, Tv> implements EmptyKeyedContainerFactory<Tk, Tv, ?Map<Tk, Tv>> {
	public function __construct() {}
	public function make_container(): ?Map<Tk, Tv> {
		return Map{};
	}
}