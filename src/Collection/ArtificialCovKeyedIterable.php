<?hh // strict
namespace HHRx\Collection;
use HHRx\Collection\CovKeyedContainerWrapper as CovKC;
abstract class ArtificialCovKeyedIterable<+Tk, +Tv, +TWrapper as CovKC<Tk, Tv>> implements Iterable<Tv> {
	public function __construct(private classname<TWrapper> $wrapper_class) {}
	abstract public function getIterator(): KeyedIterator<Tk, Tv>;
	
	public function get_wrapper(): classname<TWrapper> {
		return $this->wrapper_class;
	}
	
	public function nullable_reduce<TInitial>((function(?TInitial, Tv): TInitial) $f, ?TInitial $initial): ?TInitial {
		return $this->reduce($f, $initial);
	}
	public function reduce<TInitial>((function(?TInitial, Tv): TInitial) $f, TInitial $initial): TInitial {
		return $this->keyed_reduce((?TInitial $prev, Pair<Tk, Tv> $next) ==> $f($prev, $next[1]), $initial);
	}
	public function nullable_keyed_reduce<TInitial>((function(?TInitial, Pair<Tk, Tv>): ?TInitial) $f, TInitial $initial): ?TInitial {
		return $this->keyed_reduce($f, $initial);
	}
	public function keyed_reduce<TInitial>((function(?TInitial, Pair<Tk, Tv>): TInitial) $f, TInitial $initial): TInitial {
		foreach($this->getIterator() as $k => $v)
			$initial = $f($initial, Pair{$k, $v});
		return $initial;
	}
	
	public function concat<Tu super Tv>(Traversable<Tu> $incoming): \ConstVector<Tu> {
		$V = (Vector{})->concat($incoming);
		foreach($this->getIterator() as $k => $unit)
			$V->add($unit);
		return $V;
	}
	public function filter((function(Tv): bool) $fn): TWrapper {
		$M = Map{};
		foreach($this->getIterator() as $k=>$unit)
			if($fn($unit))
				$M[$k] = $unit;
		$wrapper_class = $this->wrapper_class;
		return new $wrapper_class($M);
	}
	public function filterWithKey((function(Tk, Tv): bool) $fn): TWrapper {
		$M = Map{};
		foreach($this->getIterator() as $k=>$unit)
			if($fn($k, $unit))
				$M[$k] = $unit;
		$wrapper_class = $this->wrapper_class;
		return new $wrapper_class($M);
	}
	public function firstValue(): ?Tv {
		$v = null;
		foreach($this->getIterator() as $v)
			return $v;
		return null;
	}
	public function firstKey(): ?Tk {
		$k = null;
		foreach($this->getIterator() as $k => $_)
			return $k;
		return null;
	}
	public function lastValue(): ?Tv {
		$v = null; // oh yeah? well... your MOM is defined in a different scope >__>
		foreach($this->getIterator() as $v) {}
		return $v;
	}
	public function lastKey(): ?Tk {
		$k = null;
		foreach($this->getIterator() as $k => $_) {}
		return $k;
	}
	public function lazy(): this { // ... : this maybe?
		return $this; // isn't this already as lazy as it can get? o_O I mean really, what is `Iterable::lazy()` meant to do anyways?
	}
	// <<__Deprecated('For more flexible return type, reduce on the empty container of your choice.')>>
	public function map<Tu>((function(Tv): Tu) $fn): ArtificialCovKeyedIterable<Tk, Tu, CovKC<Tk, Tu>> {
		$M = Map{};
		foreach($this->getIterator() as $k=>$unit)
			$M[$k] = $fn($unit);
		return new CovKC($M);
	}
	// <<__Deprecated('For more flexible return type, reduce on the empty container of your choice.')>>
	public function mapWithKey<Tu>((function(Tk, Tv): Tu) $fn): ArtificialCovKeyedIterable<Tk, Tu, CovKC<Tk, Tu>> {
		$M = Map{};
		foreach($this->getIterator() as $k=>$unit)
			$M[$k] = $fn($k, $unit);
		return new CovKC($M);
	}
	public function skip(int $n): this {
		$copy = clone $this;
		foreach($copy as $_)
			if($n-- === 0)
				break; // note: although the iterator state is stored only in this class, KeyedContainer is not Iterable so we don't need to keep two iterators in sync
		return $copy;
	}
	public function skipWhile((function(Tv): bool) $fn): this {
		$copy = clone $this;
		foreach($copy as $unit)
			if(!$fn($unit))
				break;
		return $copy;
	}
	public function slice(int $start, int $len): TWrapper {
		$M = Map{};
		$iterator = $this->skip($start)->getIterator();
		for(; $len > 0 && $iterator->valid(); $len-- && $iterator->next())
			$M[$iterator->key()] = $iterator->current();
		$wrapper_class = $this->wrapper_class;
		return new $wrapper_class($M);
	}
	public function take(int $n): TWrapper {
		return $this->slice(0, $n);
	}
	public function takeWhile((function(Tv): bool) $until): TWrapper {
		$M = Map{};
		foreach($this->getIterator() as $k => $v)
			if($until($v))
				$M[$k] = $v;
			else
				break;
		$wrapper_class = $this->wrapper_class;
		return new $wrapper_class($M);
	}
	/* HH_IGNORE_ERROR[4045] Oddly, the Iterable interface doesn't parameterize the array, but its sitting sheltered in a decl. */
	public function toArray(): array {
		$A = array();
		foreach($this->getIterator() as $k => $v)
			$A[$k] = $v;
		return $A;
	}
	/* HH_IGNORE_ERROR[4045] Oddly, the Iterable interface doesn't parameterize the array, but its sitting sheltered in a decl. */
	public function toValuesArray(): array {
		// no keys anyways lol
		return $this->toArray();
	}
	/* HH_IGNORE_ERROR[4045] Oddly, the Iterable interface doesn't parameterize the array, but its sitting sheltered in a decl. */
	public function toKeysArray(): array {
		// no keys anyways lol
		$A = array();
		foreach($this->getIterator() as $k => $v)
			array_push($A, $k);
		return $A;
	}
	public function toImmSet(): ImmSet<Tv> {
		return new ImmSet($this->toMap());
	}
	public function toImmVector(): ImmVector<Tv> {
		return new ImmVector($this->toMap());
	}
	/* HH_FIXME[4120]: While this violates our variance annotations, we are
   * returning a copy of the underlying collection, so it is actually safe.
   * See #6853603. */
	public function toSet(): Set<Tv> {
		return new Set($this->toMap());
	}
	/* HH_FIXME[4120]: While this violates our variance annotations, we are
   * returning a copy of the underlying collection, so it is actually safe.
   * See #6853603. */
	public function toVector(): Vector<Tv> {
		return new Vector($this->toMap());
	}
	/* HH_FIXME[4120]: While this violates our variance annotations, we are
   * returning a copy of the underlying collection, so it is actually safe.
   * See #6853603. */
	public function toMap(): Map<Tk, Tv> {
		$M = Map{};
		foreach($this->getIterator() as $k => $v)
			$M[$k] = $v;
		return $M;
	}
	public function values(): this {
		return $this; // again, like with lazy... why use this?!
	}
	public function keys(): Iterable<Tk> {
		$V = Vector{};
		foreach($this->getIterator() as $k => $_)
			$V->add($k);
		return $V;
	}
	public function zip<Tu>(Traversable<Tu> $incoming): \ConstMap<Tk, Pair<Tv, Tu>> {
		$iterator = $this->getIterator();
		$M = Map{};
		foreach($incoming as $v) {
			if($iterator->valid()) {
				$M[$iterator->key()] = Pair{$iterator->current(), $v};
				$iterator->next();
			}
			else
				break;
		}
		return $M;
	}
}