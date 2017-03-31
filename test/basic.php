<?hh
use \HHReactor\Collection\Producer;
require_once __DIR__ . '/../vendor/hh_autoload.php';
\HH\Asio\join(async {
	foreach(Producer::merge(Vector{
		async { yield 1; await \HH\Asio\later(); yield 2; },
		async {
			await \HH\Asio\later(); yield 3; yield 4;
		}}) await as $v) {
		// var_dump($v);
	}
});