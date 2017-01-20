<?hh
require_once __DIR__ . '/../vendor/autoload.php';
async function finite_tick(int $delay): AsyncIterator<int> {
	for($i = 0; $i < 2; $i++) {
		await \HH\Asio\usleep($delay);
		yield $i;
	}
}
async function finite_tickish(): AsyncIterator<int> {
	for($i = 0; $i < 2; $i++) {
		await \HH\Asio\later();
		yield $i;
	}
}
$factory = new HHRx\StreamFactory();

$streams = Vector{ $factory->make(finite_tick(10000)), $factory->make(finite_tick(922337203685))};
$river = $factory->merge($streams);
$streams->map((\HHRx\Stream<int> $stream) ==> $stream->subscribe(async (int $v) ==> var_dump($v)));
$river->subscribe(async (int $v) ==> var_dump($v));
\HH\Asio\join($factory->get_total_awaitable());