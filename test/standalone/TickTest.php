<?hh
require_once __DIR__ . '/../vendor/autoload.php';
$factory = new HHReactor\StreamFactory();
$streams = Vector{ $factory->tick(500000), $factory->tick(1000000) };
$river = $factory->merge($streams);
$river->subscribe(async (int $v) ==> {
	var_dump($v);
});
$streams->map((\HHReactor\Stream<int> $stream) ==> $stream->subscribe(async (int $v) ==> var_dump($v)));
HH\Asio\join($factory->get_total_awaitable());