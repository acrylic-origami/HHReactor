<?hh // strict
namespace HHRx\Tree;
use HHRx\Collection\KeyedContainerWrapper as KC;
abstract class UnresolvedTree<+Tv, +Tx as arraykey> extends Tree<Tv, Tx> {
	private ?Tv $v;
	public function __construct(KC<Tx, this> $forest, private (function(?KC<Tx, Tv>): Tv) $resolver) {
		parent::__construct($forest, null);
	}
	<<__Override>>
	public function get_v(): ?Tv {
		return $this->v;
	}
	public function resolve(bool $_force = false): Tv {
		if(!$_force && $this->v !== null)
			return $this->v;
		else {
			$resolver = $this->resolver;
			$forest = $this->get_forest();
			if(!is_null($forest))
				return $this->v = $resolver($forest->map((this $node) ==> $node->resolve()));
			else
				return $this->v = $resolver(null);
		}
	}
}