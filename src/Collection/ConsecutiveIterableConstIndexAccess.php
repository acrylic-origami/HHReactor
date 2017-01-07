<?hh // strict
namespace HHRx\Collection;
interface ConsecutiveIterableConstIndexAccess<+Tv> extends \ConstIndexAccess<int, Tv>, KeyedIterable<int, Tv> { 
	public function count(): int;
}