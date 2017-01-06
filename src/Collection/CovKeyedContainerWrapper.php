<?hh // strict
namespace HHRx\Collection;
// class KeyedContainerWrapper<+Tk, +Tv, +KeyedContainer<Tk, Tv> as KeyedContainer<Tk, Tv>>
<<__ConsistentConstruct>>
class CovKeyedContainerWrapper<+Tk, +Tv> extends ArtificialCovKeyedIterable<Tk, Tv, this> {
	// private EmptyKeyedContainerProducer<Tk, Tv, KeyedContainer<Tk, Tv>> $empty_producer;
	public function __construct(
		private KeyedContainer<Tk, Tv> $units
		// private EmptyKeyedContainerFactory<Tk, Tv, KeyedContainer<Tk, Tv>> $empty_container_factory
		) {
		parent::__construct(static::class);
	}
	public function get_units(): KeyedContainer<Tk, Tv> {
		return $this->units;
	}
	public function getIterator(): KeyedIterator<Tk, Tv> {
		foreach($this->units as $k => $v)
			yield $k => $v;
	}
	
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
	/*
	Typing the return as a [Imm]Map is invalid because at least they are both invariant on Tk. If Hack had persistent data structures, then we could extend and trim immutable objects like KeyedContainer, but the only liberty we have for the timebeing and the forseeable future is to create new objects (fine in this case), since the cast to immutable is one-way, and immutable bars extension, or in the case of ImmVector, fast extension (concat copies)
	*/
	
	// public function filter((function(Tv): bool) $f): ?KeyedContainer<Tk, Tv> {
	// 	return $this->keyed_filter((Pair<Tk, Tv> $v) ==> $f($v[1]));
	// }
}