<?hh // strict
namespace HHReactor\Collection;
interface IHaltable {
	public function soft_halt(?\Exception $e = null): void;
}