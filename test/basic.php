<?hh
use \HHReactor\Collection\Producer;
require_once __DIR__ . '/../vendor/hh_autoload.php';
$producer = Producer::create(async {
	for($i = 0; $i < 10; $i++) {
		// await \HH\Asio\later();
		yield $i;
		// var_dump($i);
	}
})
            ->group_by((int $v) ==> intval($v > 4));
$producer = Producer::zip($producer, Producer::count_up(), 
	(Producer<int> $A, int $B) ==> $A->map((int $v) ==> sprintf('P%d - %d', $B, $v)));
//             ->flat_map(($I) ==> $I);
\HH\Asio\join(async {
	$V = await $producer->collapse();
	var_dump($V);
	var_dump(new \stdClass());
});