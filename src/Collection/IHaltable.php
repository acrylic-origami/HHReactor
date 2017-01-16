<?hh // strict
namespace HHRx\Collection;
interface IHaltable {
	public async function halt(?\Exception $e = null): Awaitable<void>;
}