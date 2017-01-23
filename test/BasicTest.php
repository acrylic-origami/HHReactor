<?hh // partial
require_once(__DIR__ . '/../vendor/hh_autoload.php');
async function f<T>(T $v): AsyncIterator<T> {
	await HH\Asio\later();
	yield $v;
}
$factory = new HHRx\StreamFactory();
$streams = Vector{ $factory->make(f(1)), $factory->make(f(2)) };
$river = $factory->merge($streams);
$streams->mapWithKey((int $k, HHRx\Stream $stream) ==> {
	$stream->subscribe(async (int $v) ==> {
		printf("Stream %d: %d\n", $k, $v);
	});
	$stream->onEnd(async () ==> {
		printf("Stream %d: END\n", $k);
	});
});
$river->subscribe(async (int $v) ==> {
	printf("River: %d\n", $v);
});
$river->onEnd(async () ==> {
	echo "RIVER: END\n";
});
\HH\Asio\join($factory->get_total_awaitable());