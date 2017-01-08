<?hh // strict
namespace HHRx\Collection;
<<__ConsistentConstruct>>
abstract class KeyedContainerWrapper<+Tk, +Tv, +TCollection as \KeyedContainer<Tk, Tv>> extends ArtificialKeyedIterable<Tk, Tv, TCollection> {
	public function __construct(private ?TCollection $units = null);
	
	<<__Override>>
	abstract public function map<Tu>((function(Tv): Tu) $fn): KeyedContainerWrapper<Tk, Tu, KeyedContainer<Tk, Tu>>;
	<<__Override>>
	abstract public function mapWithKey<Tu>((function(Tk, Tv): Tu) $fn): KeyedContainerWrapper<Tk, Tu, KeyedContainer<Tk, Tu>>;
	
	public function get_units(): TCollection {
		$units = $this->units;
		invariant(!is_null($units), 'The units here can\'t actually be null, but TCollection is not instantiable to a default value.');
		return $units;
	}
	public function getIterator(): KeyedIterator<Tk, Tv> {
		if(!is_null($this->units))
			foreach($this->get_units() as $k => $v)
				yield $k => $v;
	}
}