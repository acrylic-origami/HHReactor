<?hh // strict
namespace HHReactor\Collection;
interface ReduceableKeyedIterable<Tk, +Tv> extends KeyedIterable<Tk, Tv> {
	public function nullable_reduce<TInitial>((function(?TInitial, Tv): TInitial) $f, ?TInitial $initial): ?TInitial;
	public function reduce<TInitial>((function(?TInitial, Tv): TInitial) $f, TInitial $initial): TInitial;
	public function nullable_keyed_reduce<TInitial>((function(?TInitial, Pair<Tk, Tv>): ?TInitial) $f, TInitial $initial): ?TInitial;
	public function keyed_reduce<TInitial>((function(?TInitial, Pair<Tk, Tv>): TInitial) $f, TInitial $initial): TInitial;
}