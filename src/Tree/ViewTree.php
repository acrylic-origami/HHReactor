<?hh // strict
namespace HHRx\Tree;
use \HHRx\Collection\KeyedContainerWrapper as KC;
use \HHRx\Collection\MapW;
class ViewTree<+Tv, Tx as arraykey> extends Tree<(Tv, ?arraykey), Tx> {
	public function get_view(): ?Tv {
		$v = $this->get_v();
		if(!is_null($v))
			return $v[0];
		else
			return null;
	}
	public function get_score_tree(): Tree<?arraykey, Tx> {
		$forest = $this->get_forest();
		$v = $this->get_v();
		$score = null;
		if(!is_null($v))
			list($_, $score) = $v;
		if(!is_null($forest))
			/* HH_FIXME[4110] Should be typesafe, because `Tree` is a variant class. See https://github.com/facebook/hhvm/issues/7584#issuecomment-271130614 */
			return new Tree($forest->map((this $subtree) ==> $subtree->get_score_tree()), $score);
		else
			return new Tree(new MapW(Map{}), $score);
	}
}