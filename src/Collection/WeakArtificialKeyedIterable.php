<?hh // strict
namespace HHRx\Collection;
use HHRx\Collection\KeyedContainerWrapper as KC;
use HHRx\Collection\MutableKeyedContainerWrapper as MutableKC;
/* HH_IGNORE_ERROR[4120] KeyedIterable would be covariant on Tk if ImmMap (cf. toImmMap) weren't invariant on it because of its getter. */
abstract class WeakArtificialKeyedIterable<+Tk, +Tv> implements KeyedIterable<Tk, Tv> {
	abstract public function getIterator(): KeyedIterator<Tk, Tv>;
	
	// weak `new`-studded methods
	public function slice(int $start, int $len): KC<Tk, Tv, \ConstMap<Tk, Tv>> {
		$ret = new MapW();
		$iterator = $this->getIterator();
		for(; $len > 0 && $iterator->valid(); (($start-- > 0) ?: $len--) && $iterator->next())
			$ret->set($iterator->key(), $iterator->current());
		return $ret;
	}
	public function takeWhile((function(Tv): bool) $until): KC<Tk, Tv, \ConstMap<Tk, Tv>> {
		$ret = new MapW();
		for($iterator = $this->getIterator(); $until($iterator->current()); $iterator->next()) {
			$ret->set($iterator->key(), $iterator->current());
		}
		return $ret;
	}
	public function filter((function(Tv): bool) $fn): KC<Tk, Tv, \ConstMap<Tk, Tv>> {
		$ret = new MapW();
		foreach($this->getIterator() as $k => $v)
			if($fn($v))
				$ret->set($k, $v);
		return $ret;
	}
	public function filterWithKey((function(Tk, Tv): bool) $fn): KC<Tk, Tv, \ConstMap<Tk, Tv>> {
		$ret = new MapW();
		foreach($this->getIterator() as $k => $v)
			if($fn($k, $v))
				$ret->set($k, $v);
		return $ret;
	}
	public function map<Tu>((function(Tv): Tu) $fn): MutableKC<Tk, Tu, \MutableKeyedContainer<Tk, Tu>> {
		$ret = new MapW();
		foreach($this->getIterator() as $k => $v)
			$ret->set($k, $fn($v));
		return $ret;
	}
	public function mapWithKey<Tu>((function(Tk, Tv): Tu) $fn): MutableKC<Tk, Tu, \MutableKeyedContainer<Tk, Tu>> {
		$ret = new MapW();
		foreach($this->getIterator() as $k => $v)
			$ret->set($k, $fn($k, $v));
		return $ret;
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
	
	<<__Deprecated('Result is automatically immutable even if underlying collection is not. Implement this in the parent scope or use subclass methods instead.')>>
	public function concat<Tu super Tv>(Traversable<Tu> $incoming): \ConstVector<Tu> {
		$V = (Vector{})->concat($incoming);
		foreach($this->getIterator() as $k => $unit)
			$V->add($unit);
		return $V;
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
	public function take(int $n): KC<Tk, Tv, \ConstMap<Tk, Tv>> {
		return $this->slice(0, $n);
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
   * See #6853603. Furthermore, see note at top.
   * This is the one annotation that doesn't exist in the hhi. */
	public function toImmMap(): ImmMap<Tk, Tv> {
		return new ImmMap($this->toMap());
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
	<<__Deprecated('Result is automatically immutable even if underlying collection is not. Implement this in the parent scope or use subclass methods instead.')>>
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