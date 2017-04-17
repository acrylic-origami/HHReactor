<?hh // strict
namespace HHReactor;
interface CovWrapper<+T> {
	public function get(): T;
}