<?hh // strict
namespace HHRx\Collection;
interface ConsecutiveIterableIndexAccess<Tv> extends \IndexAccess<int, Tv>, ConsecutiveIterableConstIndexAccess<Tv> {
	public function add(Tv $v): this;
}