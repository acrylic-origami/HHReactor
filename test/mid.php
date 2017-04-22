<?hh
use \HHReactor\Collection\Producer;
require_once __DIR__ . '/../vendor/hh_autoload.php';
/* HH_IGNORE_ERROR[2012] */
async function produce(int $j): AsyncIterator<int> {
	for($i = 0; $i < $j; $i++) {
		yield $i;
		await \HH\Asio\usleep(intval(rand()/getrandmax() * 1000000.0)); // every now and then, do some work
	}
}

$root = Producer::create(produce(15));
$gt_4 = (clone $root)->group_by((int $v) ==> $v % 3);
$gt_4 = Producer::zip($gt_4, Producer::count_up(), 
	(Producer<int> $A, int $B) ==> $A->map((int $v) ==> sprintf('%d %% 3 = %d', $v, $B)))
            ->flat_map(($I) ==> $I); // collapse back to one producer

// Get the last odd value
$last_odd = (clone $root)->filter((int $v) ==> (bool)($v % 2))
                         ->last();
\HH\Asio\join(async {
	foreach($gt_4 await as $v)
		var_dump($v);
	
	$last_odd = await $last_odd; // just await the single value
	var_dump($last_odd);
});