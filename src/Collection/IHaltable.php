<?hh // strict
namespace HHRx\Collection;
interface IHaltable {
	public function halt(?\Exception $e = null): Awaitable<void>;
}