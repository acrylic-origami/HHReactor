<?hh // strict
namespace HHRx\Collection;
class VectorIA<Tv> extends IterableIndexAccess<int, Tv, Vector<Tv>> {
	public function __construct(Vector<Tv> $collection = Vector{}) {
		parent::__construct($collection, \Vector::fromKeysOf($collection));
	}
	public function add(Tv $v): this {
		$units = $this->get_units();
		invariant(!is_null($units), 'Cannot `setAll` on null collection.');
		$units->add($v);
		$this->keys->add($this->keys->count());
		return $this;
	}
}