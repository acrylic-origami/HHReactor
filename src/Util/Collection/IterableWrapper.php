<?hh // strict
namespace HHRx\Util\Collection;
interface IterableWrapper<+Tx as Iterable<Tv>, +Tv> {
	public function reduce<Tf>((function(Tf, Tv): Tf) $f, Tf $initial): Tf;
	// public function flatten(): Tv;
}