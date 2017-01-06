<?hh // strict
namespace HHRx\Collection;
class VectorWrapper<T> extends KeyedIterableWrapper<Vector<T>, int, T> {
	public function __construct(
		public Vector<T> $units = Vector{}
		) {}
	public function empty(): bool {
		return $this->units->count() === 0;
	}
}