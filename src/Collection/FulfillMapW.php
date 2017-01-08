<?hh // strict
namespace HHRx\Collection;
use HHRx\Collection\KeyedContainerWrapper as KC;
trait FulfillMapW<Tk, Tv> {
	require extends KC<Tk, Tv, Map<Tk, Tv>>;
	public function __construct(?Map<Tk, Tv> $units = null) {
		parent::__construct($units ?? Map{});
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
	public function filterWithKey((function(Tk, Tv): bool) $fn): this {
		return new static($this->get_units()->filterWithKey($fn));
	}
	<<__Override>>
	public function map<Tu>((function(Tv): Tu) $fn): KC<Tk, Tu, Map<Tk, Tu>> {
		return new static($this->get_units()->map($fn));
	}
	<<__Override>>
	public function mapWithKey<Tu>((function(Tk, Tv): Tu) $fn): KC<Tk, Tu, Map<Tk, Tu>> {
		return new static($this->get_units()->mapWithKey($fn));
	}
}