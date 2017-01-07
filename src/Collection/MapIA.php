<?hh // strict
namespace HHRx\Collection;
class MapIA<Tk as arraykey, Tv> extends ConstMapCIA<Tk, Tv, Map<Tk, Tv>, Vector<Tk>, VectorKeys, VectorIA<Tk>> implements \IndexAccess<Tk, Tv> {
	use IterableIndexAccessMutators<Tk, Tv, Map<Tk, Tv>, VectorIA<Tk>>;
	public function __construct(Map<Tk, Tv> $collection = Map{}, ?VectorIA<Tk> $keys = null) {
		parent::__construct(new Map($collection), $keys ?? new VectorIA(Vector::fromKeysOf($collection)));
	}
	<<__Override>>
	public function setAll(?KeyedTraversable<Tk, Tv> $incoming): this {
		// [EDGE-CASED] no unique keys: no need to modify $this->keys;
		// Map is the one exception (see https://docs.hhvm.com/hack/reference/class/HH.Map/setAll/)
		if(!is_null($incoming)) {
			$units = $this->get_units();
			invariant(!is_null($units), 'Cannot `setAll` on null collection.');
			foreach($incoming as $k => $_)
				if(!$units->containsKey($k))
					$this->keys()->add($k);
			$units->setAll($incoming);
		}
		return $this;
	}
}