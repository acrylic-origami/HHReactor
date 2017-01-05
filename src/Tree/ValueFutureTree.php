<?hh // strict
abstract class ValueFutureTree<Tk as arraykey, Tv> {
	public function __construct(
		protected ?Map<Tk, this> $subtree,
		protected ?Awaitable<Tv> $v) {}
	public async function get(Tk $k): this {
		invariant(!is_null($this->subtree), 'Subtree is null, cannot FKT::get.');
		$subtree = $this->subtree; // eff the typechecker sometimes.
		if($subtree->containsKey($k))
			return $subtree[$k];
		else
			throw new \OutOfBoundsException('Key `'.$k.'` not found in FKT::get.');
	}
	public async function resolve(): Awaitable<Tv> {
		return await ($this->v ?? ($this->v = $this->_resolve())); // the memoizing function
	}
	abstract protected async function _resolve(): Awaitable<Tv>;
}