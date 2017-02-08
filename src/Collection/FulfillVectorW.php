<?hh // strict
namespace HHReactor\Collection;
use HHReactor\Collection\KeyedContainerWrapper as KC;
trait FulfillVectorW<Tv> {
	require extends KC<int, Tv, Vector<Tv>>;
	public function __construct(?Vector<Tv> $units = null) {
		parent::__construct($units ?? Vector{});
	}
	public function add(Tv $v): this {
		$this->get_units()->add($v);
		return $this;
	}
	<<__Override>>
	public function slice(int $start, int $len): this {
		return new static($this->get_units()->slice($start, $len));
	}
	<<__Override>>
	public function takeWhile((function(Tv): bool) $until): this {
		return new static($this->get_units()->takeWhile($until));
	}
	<<__Override>>
	public function filter((function(Tv): bool) $fn): this {
		return new static($this->get_units()->filter($fn));
	}
	<<__Override>>
	public function filterWithKey((function(int, Tv): bool) $fn): this {
		return new static($this->get_units()->filterWithKey($fn));
	}
	<<__Override>>
	public function map<Tu>((function(Tv): Tu) $fn): KC<int, Tu, Vector<Tu>> {
		return new static($this->get_units()->map($fn));
	}
	<<__Override>>
	public function mapWithKey<Tu>((function(int, Tv): Tu) $fn): KC<int, Tu, Vector<Tu>> {
		return new static($this->get_units()->mapWithKey($fn));
	}
}