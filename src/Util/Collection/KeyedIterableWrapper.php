<?hh // strict
namespace HHRx\Util\Collection;
abstract class KeyedIterableWrapper<Tu, +Tv, +T as KeyedIterable<Tu, Tv>> implements IterableWrapper<T, Tv> {
	public function __construct(private T $units) {}
	
	public function get_units(): T {
		return $this->units;
	}
	
	public function reduce<Tf>(
		(function(Tf, Tv): Tf) $f, 
		Tf $initial
		): Tf {
		$target = $this->units->getIterator();
		while($target->valid()) {
			$initial = $f($initial, $target->current());
			$target->next();
		}
		return $initial;
		
		// return $this->_reduce_step(
		// 	(Tv $initial, KeyedIterator<Tu, Tv> $target) ==> $f($initial, $target->current()),
		// 	$f, $initial, $target ?: $this->units->getIterator()
		// );
	}
	public function keyed_reduce<Tf>(
		(function(Tf, Pair<Tu, Tv>): Tf) $f, 
		Tf $initial, 
		KeyedIterator<Tu, Tv> $target = $this->units->getIterator()): Tf {
		
		if(!$target->valid())
			return $initial;
		
		$next = $f($initial, Pair{$target->key(), $target->current()});
		$target->next();
		
		return $this->keyed_reduce($f, $next, $target);
		
		// return $this->_reduce_step(
		// 	(Tv $initial, KeyedIterator<Tu, Tv> $target) ==> $f($initial, Pair{$target->key(), $target->current()}),
		// 	$f, $initial, $target ?: $this->units->getIterator()
		// );
	}
	public function iterate((function(Tv, Tu): void) $f): void {
		$iterator = $this->units->getIterator();
		while($iterator->valid()) {
			$f($iterator->current(), $iterator->key());
			$iterator->next();
		}
	}
	protected function _reduce_step(
		(function(Tv, KeyedIterator<Tu, Tv>): Tv) $next, 
		(function(Tv, mixed): Tv) $f,
		Tv $initial,
		KeyedIterator<Tu, Tv> $target): Tv {
		// generic Tv incompatible with mixed?!
		
		if(!$target->valid())
			return $initial;
		
		$N = $next($initial, $target);
		$target->next();
		
		return $this->_reduce_step($next, $f, $N, $target);
	}
	
	public function coalesce<Tr>((function(?Tv): ?Tr) $fn): ?Tv {
		$iterator = $this->units->getIterator();
		while($iterator->valid() && !is_null($fn($iterator->current())))
			$iterator->next();
		if(!$iterator->valid())
			return null;
		else
			return $iterator->current();
	}
}