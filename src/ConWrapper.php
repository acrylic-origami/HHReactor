<?hh // strict
namespace HHReactor;
interface ConWrapper<-T> {
	public function set(T $v): void;
}