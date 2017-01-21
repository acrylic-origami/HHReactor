<?hh // partial
require_once(__DIR__ . '/../vendor/hh_autoload.php');
function f(): void {}
$factory = new HHRx\StreamFactory();
$stream = $factory->merge(Vector{ $factory->make(async {
	await \HH\Asio\later();
	yield 1;
	await \HH\Asio\later();
	yield 2;
}) });
// HH\Asio\join(async {
// 	f();
// 	foreach($stream->clone_producer()->get_iterator() await as $v) {
// 		var_dump($v);
// 	}
// });
$stream->subscribe(async (int $v) ==> {
	printf("V: %d\n", $v);
});
\HH\Asio\join($factory->get_total_awaitable());