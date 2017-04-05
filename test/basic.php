<?hh
use \HHReactor\Collection\Producer;
require_once __DIR__ . '/../vendor/hh_autoload.php';
$producer = Producer::create(async {
	for($i = 0; $i < 10; $i++) {
		await \HH\Asio\later();
		yield $i;
		// var_dump($i);
	}
})
            ->group_by((int $v) ==> intval($v > 4))
            ->map(($P) ==> $P->last());
\HH\Asio\join(async {
	$V = await $producer->collapse();
	$V = await \HH\Asio\v($V);
	var_dump($V);
});
/* 
*/