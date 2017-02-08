<?hh // strict
namespace HHReactor\Collection;
interface IHaltable {
	public function halt(?\Exception $e = null): Awaitable<void>;
}