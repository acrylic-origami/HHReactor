<?hh // strict
namespace HHRx\Collection;
<<__ConsistentConstruct>>
class ConstVectorKeys extends ArtificialKeyedIterable<int, int> implements ConsecutiveIterableConstIndexAccess<int> {
	public function __construct(protected int $count = 0) {}
	public function count(): int {
		return $this->count;
	}
	public function containsKey<Tu super int>(Tu $k): bool {
		return is_int($k) && $k < $this->count;
	}
	public function at(int $k): int {
		if($k > $this->count)
			throw new \OutOfBoundsException(sprintf('Integer key %d is out of bounds', $k)); // throw standard Vector::set exception
		return $k;
	}
	public function get(int $k): ?int {
		if($k > $this->count)
			return null;
		return $k;
	}
	
	public function getIterator(): KeyedIterator<int, int> {
		for($i = 0; $i < $this->count; $i++)
			yield $i => $i;
	}
}