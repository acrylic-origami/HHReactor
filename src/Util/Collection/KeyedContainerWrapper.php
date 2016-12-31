<?hh // strict
namespace HHRx\Util\Collection;
// class KeyedContainerWrapper<+Tk, +Tv, +TCollection as KeyedContainer<Tk, Tv>>
class KeyedContainerWrapper<+Tk, +Tv> extends TraversableWrapper<Tv, KeyedContainer<Tk, Tv>> implements Iterable<Tv> {
	
	/* HH_IGNORE_ERROR[4120] Waiting for <<__Const>>, then this will be fine. */
	public function key_exists(Tk $k): bool {
		try {
			$units = $this->get_units();
			invariant(!is_null($units), 'Cannot check keys on null collection.');
			$units[$k];
			return true;
		}
		catch(\OutOfBoundsException $e) {
			return false;
		}
	}
	
	//** Functional methods **//
	public function reduce<TInitial>((function(?TInitial, Tv): ?TInitial) $f, TInitial $initial): ?TInitial {
		return $this->keyed_reduce((?TInitial $prev, Pair<Tk, Tv> $next) ==> $f($prev, $next[1]), $initial);
	}
	public function keyed_reduce<TInitial>((function(?TInitial, Pair<Tk, Tv>): ?TInitial) $f, TInitial $initial): ?TInitial {
		return $this->keyed_reduce_until($f, (Tv $v) ==> false, $initial);
		// if(!is_null($this->get_units())) {
		// 	foreach($this->get_units() as $k => $unit) {
		// 		$initial = $f($initial, Pair{$k, $unit});
		// 	}
		// 	return $initial;
		// }
		// else {
		// 	return null;
		// }
	}
	public function reduce_until<TInitial>((function(?TInitial, Tv): ?TInitial) $f, (function(Tv): bool) $until, ?TInitial $initial): ?TInitial {
		return $this->keyed_reduce_until((?TInitial $prev, Pair<Tk, Tv> $next) ==> $f($initial, $next[1]), $until, $initial);
	}
	public function keyed_reduce_until<TInitial>((function(?TInitial, Pair<Tk, Tv>): ?TInitial) $f, (function(Tv): bool) $until, ?TInitial $initial): ?TInitial {
		$units = $this->get_units();
		if(!is_null($units)) {
			foreach($units as $k => $unit) {
				if(!$until($unit))
					$initial = $f($initial, Pair{$k, $unit});
				else
					break;
			}
			return $initial;
		}
		else {
			return null;
		}
	}
	
	// see far below keyed_filter implementation
	
	// public function keyed_filter((function(Pair<Tk, Tv>): bool) $fn): KeyedContainer<Tk, Tv> {
	// 	return $this->keyed_reduce((Map<Tk, Tv> $prev, Pair<Tk, Tv> $next) ==> $fn($next) ? $prev->add($next) : $prev, Map{});
	// }
	
	// Unless `keyed_filter` is used concretely, it's too confusing to have this and `filter` returning different object types, but being implemented pretty much identically
	
	// public function keyed_filter((function(Pair<Tk, Tv>): bool) $f): ?KeyedContainer<Tk, Tv> {
	// 	$M = Map{};
	// 	$units = $this->get_units();
	// 	if(!is_null($units)) {
	// 		foreach($units as $k=>$unit) {
	// 			if($f(Pair{$k, $unit}))
	// 				$M[$k] = $unit;
	// 		}
	// 		return $M;
	// 	}
	// 	else
	// 		return null;
		
	// 	// return $this->keyed_reduce((Map<Tk, Tv> $prev, Pair<Tk, Tv> $next) ==> $f($next) ? $prev->add($next) : $prev, Map{}); // also works
	// 	// though I wonder if the Map<Tk, Tv> typing is okay here?
	// }
	/* HH_IGNORE_ERROR[4120] Generator should be covariant on its Tk. */
	public function yield_iterate<Tr>((function(Tv): Tr) $f): \Generator<Tk, Tr, bool> {
		return $this->keyed_yield_iterate((Pair<Tk, Tv> $v) ==> $f($v[1]));
	}
	/* HH_IGNORE_ERROR[4120] Generator should be covariant on its Tk. */
	public function keyed_yield_iterate<Tr>((function(Pair<Tk, Tv>): Tr) $f): \Generator<Tk, Tr, bool> {
		$units = $this->get_units();
		if(!is_null($units)) {
			foreach($units as $k => $unit) {
				yield $k => $f(Pair{$k, $unit});
			}
		}
	}
	/*
	Typing the return as a [Imm]Map is invalid because at least they are both invariant on Tk. If Hack had persistent data structures, then we could extend and trim immutable objects like KeyedContainer, but the only liberty we have for the timebeing and the forseeable future is to create new objects (fine in this case), since the cast to immutable is one-way, and immutable bars extension, or in the case of ImmVector, fast extension (concat copies)
	*/
	public function keyed_map<Tx>((function(Pair<Tk, Tv>): Tx) $f): KeyedContainerWrapper<Tk, Tx> {
		$M = Map{};
		$units = $this->get_units();
		if(!is_null($units)) {
			foreach($units as $k => $unit) {
				$M[$k] = $f(Pair{$k, $unit});
			}
			return new static($M);
		}
		else
			return new static();
	}
	
	// public function filter((function(Tv): bool) $f): ?KeyedContainer<Tk, Tv> {
	// 	return $this->keyed_filter((Pair<Tk, Tv> $v) ==> $f($v[1]));
	// }
	
	//** Iterable implementation **//
	
	public function concat<Tu super Tv>(Traversable<Tu> $incoming): KeyedContainerWrapper<int, Tu> {
		$ret = $this->reduce((?Vector<Tu> $prev, Tv $next) ==> {
			invariant(!is_null($prev), 'Cannot concat to null collection');
			return $prev->add($next);
		}, (Map{})->concat($incoming));
		invariant(!is_null($ret), 'Concat unexpectedly created null iterable.');
		return new static($ret);
	}
	public function filter((function(Tv): bool) $fn): this {
		$M = Map{};
		$units = $this->get_units();
		if(!is_null($units))
			foreach($units as $k=>$unit)
				if($fn($unit))
					$M[$k] = $unit;
		return new static($M);
	}
	public function firstValue(): Tv {
		$units = $this->get_units();
		invariant(!is_null($units), 'Units are null: there are no values for KeyedContainerWrapper::firstValue');
		foreach($units as $v) {
			return $v;
		}
		throw new \BadMethodCallException('Units are empty: there is no first value.');
	}
	public function getIterator(): Iterator<Tv> {
		return $this->yield_iterate((Tv $I) ==> $I);
	}
	public function lastValue(): ?Tv {
		$units = $this->get_units();
		if(is_null($units))
			return null;
		
		$v = null; // oh yeah? well... your MOM is defined in a different scope >__>
		foreach($units as $v) {}
		return $v;
	}
	public function lazy(): this { // ... : this maybe?
		return $this; // isn't this already as lazy as it can get? o_O I mean really, what is `Iterable::lazy()` meant to do anyways?
	}
	public function map<Tu>((function(Tv): Tu) $fn): KeyedContainerWrapper<Tk, Tu> {
		$M = Map{};
		$units = $this->get_units();
		if(!is_null($units))
			foreach($units as $k=>$unit)
				$M[$k] = $fn($unit);
		return new static($M);
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
	public function slice(int $start, int $len): this {
		$ret = $this->keyed_reduce_until(
			(?Map<Tk, Tv> $prev, Pair<Tk, Tv> $next) ==> {
				invariant(!is_null($prev), 'Implementation error: prev cannot be null.');
				return (($start-- <= 0 && $len-- > 0) ? $prev->add($next) : $prev);
			},
			(Tv $v) ==> $len === 0, 
			Map{}
		);
		invariant(!is_null($ret), 'Implementation error: result from slicing cannot be null.');
		return new static($ret);
	}
	public function take(int $n): this {
		$ret = $this->keyed_reduce_until(
			(?Map<Tk, Tv> $prev, Pair<Tk, Tv> $next) ==> {
				invariant(!is_null($prev), 'Implementation error: prev cannot be null.');
				return $prev->add($next);
			},
			// (Map<Tk, Tv> $prev, Pair<Tk, Tv> $next) ==> $prev->add($next),
			(Tv $v) ==> $n-- == 0,
			Map{}
		);
		invariant(!is_null($ret), 'Implementation error: result of taking cannot be null, only empty Map.');
		return new static($ret);
	}
	public function takeWhile((function(Tv): bool) $until): this {
		$ret = $this->keyed_reduce_until(
			fun('map_add'),
			// (Map<Tk, Tv> $prev, Pair<Tk, Tv> $next) ==> $prev->add($next),
			$until,
			Map{}
		);
		invariant(!is_null($ret), 'Failing this refinement is impossible because the result begins with a non-null value, unless the `keyed_reduce_until` or `map_add` implementations have changed for the worse.');
		return new static($ret);
	}
	/* HH_IGNORE_ERROR[4045] Oddly, the Iterable interface doesn't parameterize the array, but its sitting sheltered in a decl. */
	public function toArray(): array {
		$A = array();
		$units = $this->get_units();
		if(!is_null($units))
			foreach($units as $k => $v)
				$A[$k] = $v;
		return $A;
	}
	/* HH_IGNORE_ERROR[4045] Oddly, the Iterable interface doesn't parameterize the array, but its sitting sheltered in a decl. */
	public function toValuesArray(): array {
		// as tempted as I am to use array_values($this->toArray()), I'm no cheapstake for performance. For this never-to-be-used function.
		$A = array();
		$units = $this->get_units();
		if(!is_null($units))
			foreach($units as $k => $v)
				$A[] = $v;
		return $A;
	}
	public function toImmSet(): ImmSet<Tv> {
		return new ImmSet($this->get_units());
	}
	public function toImmVector(): ImmVector<Tv> {
		return new ImmVector($this->get_units());
	}
	/* HH_FIXME[4120]: While this violates our variance annotations, we are
   * returning a copy of the underlying collection, so it is actually safe.
   * See #6853603. */
	public function toSet(): Set<Tv> {
		return new Set($this->get_units());
	}
	/* HH_FIXME[4120]: While this violates our variance annotations, we are
   * returning a copy of the underlying collection, so it is actually safe.
   * See #6853603. */
	public function toVector(): Vector<Tv> {
		return new Vector($this->get_units());
	}
	public function values(): this {
		return $this; // again, like with lazy... why use this?!
	}
	public function zip<Tu>(Traversable<Tu> $incoming): Iterable<Pair<Tv, Tu>> {
		$iterator = $this->getIterator();
		$V = Vector{};
		foreach($incoming as $v) {
			if($iterator->valid()) {
				$V->add(Pair{$iterator->current(), $v});
				$iterator->next();
			}
			else
				break;
		}
		return $V;
	}
}