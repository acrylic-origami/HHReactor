<?hh // partial
require_once(__DIR__ . '/../vendor/autoload.php');

$factory = new HHReactor\StreamFactory();
$ports = Vector{ 1339, 1338, 1337 };
$streams = $ports->map((int $port) ==> (new HHReactor\Stream\HTTPStream($factory, $port))->get_local_stream());
$river = $factory->merge($streams);
$river->subscribe(async (mixed $v) ==> {
	var_dump($v);
});
// $streams[0]->subscribe(async (mixed $v) ==> {
// 	var_dump($v);
// });
// $streams[1]->subscribe(async (mixed $v) ==> {
// 	var_dump($v);
// });
HH\Asio\join(async {
	await $factory->get_total_awaitable();
});