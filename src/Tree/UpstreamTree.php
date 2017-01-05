<?hh // strict
abstract class UpstreamTree<Tv, TParent> {
	public function __construct(
		protected ?Tv $v,
		protected ?TParent $parent
		) {}
	public function resolve(): Tv {
		return $this->v ?? ($this->v = $this->_resolve()); // the memoizing function
	}
	abstract protected function _resolve(): Tv;
}