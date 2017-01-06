<?hh // strict
namespace HHRx\Collection;
interface IterableWrapper<+Tx as Iterable<Tv>, +Tv> {
	public function reduce<Tf>((function(Tf, Tv): Tf) $f, Tf $initial): Tf;
	// public function flatten(): Tv;
}