<?hh // strict
namespace HHReactor\Asio;
function lifetime(
	Vector<AsyncIterator<mixed>> $iterators = Vector{},
	Vector<Awaitable<mixed>> $awaitables = Vector{}): Awaitable<void> {
	return \HHReactor\Asio\voidify(
		\HH\Asio\v(
			$iterators->map(async ($iterator) ==> {
				foreach($iterator await as $_) {}
			})
			          ->concat($awaitables)
		)
	);
}