<?hh // strict
namespace HHRx\Tree;
use \HHRx\Collection\KeyedContainerWrapper as KC;
use \HHRx\Collection\IterableIndexAccess as IterableIA;
class MutableTree<Tv, Tx as arraykey> extends Tree<Tv, Tx> {
	public function __construct(
		protected IterableIA<Tx, this, \IndexAccess<Tx, this>> $forest,
		private ?Tv $v = null
		) {
		parent::__construct($forest, $v);
	}
	<<__Override>>
	public function get_forest(): IterableIA<Tx, this, \IndexAccess<Tx, this>> {
		return $this->forest;
	}
	public function add_subtree(Tx $k, this $incoming): void {
		$this->forest->set($k, $incoming); // not as flexible, but whatever, all subtrees are mutable so they _should_ refer to this forest.
	}
	// public function set_forest<TTree as Tree<Tv, Tx>>(KeyedContainer<Tk, TTree> $incoming): void {
	// 	$this->
	// }
}