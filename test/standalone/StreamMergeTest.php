<?hh // partial
require_once(__DIR__ . '/../vendor/hh_autoload.php');
$factory = new HHReactor\StreamFactory();
$streams = $factory->merge(Vector{ $factory->make(async { 
	await HH\Asio\usleep(1);
	yield 1;
}), $factory->make(async { 
	await HH\Asio\usleep(1);
	yield 2;
}) });
echo 'subscribing';
$streams->subscribe(async (int $v) ==> {
	echo $v."\n";
});
echo 'subscribed';
$streams->onEnd(async () ==> {
	echo 'ended';
});
HH\Asio\join($factory->get_total_awaitable());