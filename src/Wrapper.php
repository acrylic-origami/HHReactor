<?hh // strict
namespace HHReactor;
class Wrapper<T> implements CovWrapper<T>, ConWrapper<T> {
	public function __construct(public T $v) {}
	public function set(T $v): void {
		$this->v = $v;
	}
	public function get(): T {
		return $this->v;
	}
}