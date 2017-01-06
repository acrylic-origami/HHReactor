<?hh // strict
namespace HHRx\Collection;
// class KeyedContainerWrapper<+Tk, +Tv, +KeyedContainer<Tk, Tv> as KeyedContainer<Tk, Tv>>
<<__ConsistentConstruct>>
class KeyedContainerWrapper<Tk, +Tv> extends CovKeyedContainerWrapper<Tk, Tv> implements KeyedIterable<Tk, Tv> {
	public function toImmMap(): ImmMap<Tk, Tv> {
		return new ImmMap($this->toMap());
	}
	<<__Override>>
	public function map<Tu>((function(Tv): Tu) $fn): KeyedContainerWrapper<Tk, Tu> {
		$M = Map{};
		foreach($this->get_units() as $k=>$unit)
			$M[$k] = $fn($unit);
		return new static($M);
	}
	<<__Override>>
	public function mapWithKey<Tu>((function(Tk, Tv): Tu) $fn): KeyedContainerWrapper<Tk, Tu> {
		$M = Map{};
		foreach($this->getIterator() as $k=>$unit)
			$M[$k] = $fn($k, $unit);
		return new static($M);
	}
}