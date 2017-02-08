<?hh // strict
namespace HHReactor;
interface Streamlined<+T> {
	public function get_local_stream(): Stream<T>; // what access modifier? Route would prefer protected.
}