<?hh // strict
namespace HHReactor\Asio;
interface Dependent<+T> {
	public function get_dependencies(): ConstDependencies<T>;
}