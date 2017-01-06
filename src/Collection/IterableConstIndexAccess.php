<?hh // strict
namespace HHRx\Collection;
// Until both object-protected and contravariant ConstIndexAccess on Tk come along, Tk must be invariant. I need $keys (either the setter or the getter of $keys) to be at most protected so that the mutable descendants can modify it directly (protected setter, private getter) or define a private $keys and overwrite the getter so all of the methods in this class use that instance (private getter, protected setter)
use HHRx\Collection\KeyedContainerWrapper as KC;
class IterableConstIndexAccess<Tk as arraykey, +Tv, +TCollection as ?\ConstIndexAccess<Tk, Tv>> extends ArtificialKeyedIterable<Tk, Tv, KC<Tk, Tv>> implements \ConstIndexAccess<Tk, Tv> {
	protected Vector<Tk> $keys;
	public function __construct(
		private TCollection $units,
		// private EmptyConstIndexAccessFactory<Tk, Tv, TCollection> $empty_collection_factory,
		?Traversable<Tk> $keys = null
	) {
		if(!is_null($keys))
			$this->keys = new Vector($keys); // I hope this doesn't copy
		else
			$this->keys = Vector{};
		parent::__construct(KC::class);
	}
	public function get_units(): TCollection {
		return $this->units;
	}
	public function get_keys(): Vector<Tk> {
		return $this->keys;
	}
	// final protected function make_empty_container(): TCollection {
	// 	return $this->empty_container_factory->make_container();
	// }
	
	public function getIterator(): KeyedIterator<Tk, Tv> {
		$units = $this->units;
		if(!is_null($units)) {
			for($i = 0; $i < $this->keys->count(); $i++) {
				$key = $this->keys[$i];
				// invariant($units->containsKey($key), sprintf('Key %s missing -- keys of ConstIndexAccessWrapper not properly kept track of.', $key)); // instead of err-ing, just skip over - new remove policy
				if($units->containsKey($key))
					yield $key => $units->at($key);
			}
		}
	}
	
	public function at(Tk $k): Tv {
		$units = $this->units;
		if(is_null($units))
			throw new \Exception(sprintf('Key %s does not exist.', $k));
		return $units->at($k);
	}
	public function get(Tk $k): ?Tv {
		$units = $this->units;
		if(is_null($units))
			return null;
		return $units->get($k);
	}
	public function containsKey<Tu super Tk>(Tu $k): bool {
		$units = $this->units;
		return !(is_null($units) || !$units->containsKey($k));
	}
	<<__Override>>
	public function keys(): Vector<Tk> {
		return $this->keys;
	}
	<<__Override>>
	public function firstKey(): ?Tk {
		return $this->keys->firstValue();
	}
	<<__Override>>
	/* HH_IGNORE_ERROR[4045] Oddly, the Iterable interface doesn't parameterize the array, but its sitting sheltered in a decl. */
	public function toKeysArray(): array {
		return $this->keys->toValuesArray();
	}
	
}