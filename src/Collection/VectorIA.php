<?hh // strict
namespace HHRx\Collection;
class VectorIA<Tv> extends ConstVectorCIA<Tv, Vector<Tv>, VectorKeys> implements ConsecutiveIterableIndexAccess<Tv> {
	use IterableIndexAccessMutators<int, Tv, Vector<Tv>, VectorKeys>;
	public function __construct(Vector<Tv> $collection = Vector{}) {
		parent::__construct($collection, VectorKeys::class);
	}
	public function add(Tv $v): this {
		$units = $this->get_units();
		invariant(!is_null($units), 'Cannot `add` to a null collection.');
		$units->add($v);
		$this->keys()->add();
		return $this;
	}
	public function count(): int {
		return $this->keys()->count();
	}
	public function clone(): VectorIA<Tv> {
		return new self(new Vector($this->get_units()));
	}
}