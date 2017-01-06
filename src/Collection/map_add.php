<?hh // strict
namespace HHRx\Collection;
function map_add<Tk, Tv>(?Map<Tk, Tv> $M, Pair<Tk, Tv> $P, ?(function(Map<Tk, Tv>, Pair<Tk, Tv>): bool) $when = null): Map<Tk, Tv> {
	invariant(!is_null($M), 'Map passed in cannot be null.');
	if(!is_null($when) ? $when($M, $P) : true)
		return $M->add($P);
	return $M;
}