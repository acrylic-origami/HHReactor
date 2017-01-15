<?hh // strict
namespace HHRx\Collection;
// Iterable<Pair<Tk, Tv>> -> KeyedIterable<Tk, Tv>
class PairwiseKeyedContainerWrapper<+Tk, +Tv> { // WeakArtificialKeyedIterable<Tk, Tv>
	public function __construct(private Iterable<Pair<Tk, Tv>> $pairs) {}
	public function getIterator(): KeyedIterator<Tk, Tv> {
		foreach($this->pairs as $pair)
			yield $pair[0] => $pair[1];
	}
}