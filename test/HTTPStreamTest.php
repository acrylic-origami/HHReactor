<?hh // partial
require_once(__DIR__ . '/../vendor/autoload.php');

$factory = new HHRx\StreamFactory();
$ports = Vector{ 1337, 1338 };
$river = HHRx\KeyedStream::merge($ports->map((int $port) ==> (new HHRx\Stream\HTTPStream($factory, $port))->get_local_stream()));
// $http_stream = new HHRx\Stream\HTTPStream($factory, 1337);
$river->subscribe(async (mixed $v) ==> {
	var_dump($v);
});
HH\Asio\join($factory->get_total_awaitable());