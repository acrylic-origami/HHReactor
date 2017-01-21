<?hh // partial
require_once(__DIR__ . '/../vendor/hh_autoload.php');
function f(): void {}
$factory = new HHRx\StreamFactory();
$stream = $factory->merge(Vector{ $factory->make(async {
	await \HH\Asio\later();
	yield 1;
}) });
// HH\Asio\join(async {
// 	f();
// 	foreach($stream->clone_producer()->get_iterator() await as $v) {
// 		var_dump($v);
// 	}
// });
$stream->subscribe(async (int $v) ==> var_dump($v));
\HH\Asio\join($factory->get_total_awaitable());