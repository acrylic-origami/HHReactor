<?hh // strict
namespace HHRx\Collection;
// class KeyedContainerWrapper<+Tk, +Tv, +KeyedContainer<Tk, Tv> as KeyedContainer<Tk, Tv>>
<<__ConsistentConstruct>>
class KeyedContainerWrapper<+Tk, +Tv> extends ArtificialKeyedIterable<Tk, Tv> {
	// private EmptyKeyedContainerProducer<Tk, Tv, KeyedContainer<Tk, Tv>> $empty_producer;
	public function __construct(
		private KeyedContainer<Tk, Tv> $units
		// private EmptyKeyedContainerFactory<Tk, Tv, KeyedContainer<Tk, Tv>> $empty_container_factory
	) {}
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
}