<?hh // partial
require_once(__DIR__ . '/../vendor/hh_autoload.php');
async function wait_produce(int $i): AsyncKeyedIterator<int, int> {
	await HH\Asio\usleep(10000); // non-blocking 100ms
	yield $i => $i;
}
function subscribe<Tx, Tu>(HHRx\KeyedStream<Tx, Tu> $stream, string $prefix = ''): void {
	$stream->subscribe(async (Tu $v) ==> {
		usleep(100000); // intentionally BLOCKING 1000ms
		                // represents a long unit of work
		echo $prefix;
		var_dump($v);
	});
}
$factory = new HHRx\StreamFactory();
$producers = (new Vector(range(1, 3)))->map((int $v) ==> $factory->make(wait_produce($v)));
$merged = $factory->merge($producers)->keyed_transform((int $k, int $v) ==> Pair{ $k, 'MERGED '.$v });
$double_merged = $factory->merge((Vector{ $merged })->concat($producers))->keyed_transform((int $k, arraykey $v) ==> Pair{ $k, 'DOUBLE-'.$v });
$producers->map(fun('subscribe'));
subscribe($merged);
subscribe($double_merged);
HH\Asio\join($factory->get_total_awaitable());