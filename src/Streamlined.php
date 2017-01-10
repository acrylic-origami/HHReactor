<?hh // strict
namespace HHRx;
interface Streamlined<+T> {
	public function get_local_stream(): KeyedStream<mixed, T>; // what access modifier? Route would prefer protected.
}