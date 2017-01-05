<?hh // strict
namespace HHRx;
class WrappedIterable<+Tv> implements Iterable<Tv> {
	public function __construct(private Iterable<Tv> $iterable) {}
	public function get_iterable(): Iterable<Tv> {
		return $this->iterable;
	}
	public function getIterator(): Iterator<Tv> {
		return $this->iterable->getIterator();
	}
	/* HH_IGNORE_ERROR[4045] Oddly, the Iterable interface doesn't parameterize the array, but its sitting sheltered in a decl. */
	public function toArray(): array {
		return $this->iterable->toArray();
	}
	/* HH_IGNORE_ERROR[4045] Oddly, the Iterable interface doesn't parameterize the array, but its sitting sheltered in a decl. */
	public function toValuesArray(): array {
		return $this->iterable->toValuesArray();
	}
	/* HH_FIXME[4120]: While this violates our variance annotations, we are
	 * returning a copy of the underlying collection, so it is actually safe
	 * See #6853603. */
	public function toVector(): Vector<Tv> {
		return $this->iterable->toVector();
	}
	public function toImmVector(): ImmVector<Tv> {
		return $this->iterable->toImmVector();
	}
	/* HH_FIXME[4120]: While this violates our variance annotations, we are
	 * returning a copy of the underlying collection, so it is actually safe
	 * See #6853603. */
	public function toSet(): Set<Tv> {
		return $this->iterable->toSet();
	}
	public function toImmSet(): ImmSet<Tv> {
		return $this->iterable->toImmSet();
	}
	public function lazy(): Iterable<Tv> {
		return $this->iterable->lazy();
	}
	public function values(): Iterable<Tv> {
		return $this->iterable->values();
	}
	public function map<Tu>((function(Tv): Tu) $fn): Iterable<Tu> {
		return $this->iterable->map($fn);
	}
	public function filter((function(Tv): bool) $fn): Iterable<Tv> {
		return $this->iterable->filter($fn);
	}
	public function zip<Tu>(Traversable<Tu> $traversable): Iterable<Pair<Tv, Tu>> {
		return $this->iterable->zip($traversable);
	}
	public function take(int $n): Iterable<Tv> {
		return $this->iterable->take($n);
	}
	public function takeWhile((function(Tv): bool) $fn): Iterable<Tv> {
		return $this->iterable->takeWhile($fn);
	}
	public function skip(int $n): Iterable<Tv> {
		return $this->iterable->skip($n);
	}
	public function skipWhile((function(Tv): bool) $fn): Iterable<Tv> {
		return $this->iterable->skipWhile($fn);
	}
	public function slice(int $start, int $len): Iterable<Tv> {
		return $this->iterable->slice($start, $len);
	}
	public function concat<Tu super Tv>(
	  Traversable<Tu> $traversable
	): Iterable<Tu> {
	  return $this->iterable->concat($traversable);
	}
	public function firstValue(): ?Tv {
		return $this->iterable->firstValue();
	}
	public function lastValue(): ?Tv {
		return $this->iterable->lastValue();
	}
}