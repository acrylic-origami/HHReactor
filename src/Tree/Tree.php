<?hh // strict
namespace HHRx\Tree;
use \HHRx\Collection\ConstMapCIA;
// oooooh, just you wait until Tree<+Tv, Tx as arraykey, +TIterable as KeyedIterable<Tx, this>> comes along
// this won't be just any ordinary tree
// oooh no, this'll be the fucking Pando\ of abstract trees

// ...though unfortunately KeyedIterable is still invariant on Tx, and until the <<__Const>> directive is introduced, it'll stay that way.
class Tree<+Tv, Tx as arraykey> extends \HHRx\Collection\ArtificialKeyedIterable<Tx, ?Tv> {
	// private KeyedContainerWrapper<Tx, this, KeyedContainer<Tx, this>> $forest;
	// `this` disallowed as a type constraint forces the third parameter to be `KeyedContainer` rather than a generic `TCollection [as KeyedContainer<Tx, this>]`
	public function __construct(
		private ExactConstMapCIA<Tx, this> $forest,
		private ?Tv $v = null
	) {
		parent::__construct();
	}
	public function get_v(): ?Tv { // final
		// this method might or might not be final -- do I want subclasses to have their own private $vs? Smells bad: upcasting will yield a different value.
		return $this->v;
	}
	public function get_forest(): ExactConstMapCIA<Tx, this> {
		return $this->forest;
	}
	public function reduce_tree<TInitial>((function(?TInitial, ?Tv): ?TInitial) $fn, ?TInitial $initial): ?TInitial {
		// return $this->forest->reduce((?TInitial $prev, this $next) ==> $fn($next->reduce_tree($fn, $prev), $next->get_v()), $initial);
		return $fn($this->forest->reduce((?TInitial $prev, this $next) ==> $next->reduce_tree($fn, $prev), $initial), $this->v);
	}
	public function getIterator(): KeyedIterator<Tx, ?Tv> {
		foreach($this->get_forest() as $k_tree => $tree) {
			foreach($tree->getIterator() as $k => $v)
				yield $k => $v;
			yield $k_tree => $tree->get_v();
		}
	}
}