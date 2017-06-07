<?hh
namespace HHReactor\Test;
use \HHReactor\Collection\Producer;
require_once __DIR__ . '/../vendor/hh_autoload.php';

async function produce(): AsyncIterator<int> {
	for($i = 0; $i < 10; $i++) {
		yield $i;
		await \HH\Asio\usleep(intval(rand()/getrandmax() * 1000000.0)); // every now and then, do some work
	}
}
$root = Producer::create(produce());
$b1 = clone $root;
$b2 = (clone $root)->collapse();

\HH\Asio\join(\HH\Asio\va(async {
	foreach($b1 await as $v)
		var_dump($v);
}, async {
	$v = await $b2;
	var_dump($v);
}));