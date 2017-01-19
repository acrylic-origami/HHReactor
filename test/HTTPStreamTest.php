<?hh // partial
require_once(__DIR__ . '/../vendor/autoload.php');

$factory = new HHRx\StreamFactory();
$ports = Vector{ 1338, 1337 };
$streams = $ports->map((int $port) ==> (new HHRx\Stream\HTTPStream($factory, $port))->get_local_stream());
$river = $factory->merge($streams);
$river->subscribe(async (mixed $v) ==> {
	var_dump($v);
});
HH\Asio\join(async {
	await $factory->get_total_awaitable();
});