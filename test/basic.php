<?hh
use \HHReactor\Collection\Producer;
require_once __DIR__ . '/../vendor/hh_autoload.php';
$root = Producer::create(async {
	for($i = 0; $i < 10; $i++) {
		yield $i;
		if(round(rand()/getrandmax()))
			await \HH\Asio\later(); // every now and then, do some work
	}
});

// Identify values > 4
$gt_4 = (clone $root)->group_by((int $v) ==> intval($v > 4)); // bifurcate to two Producers by their >4-ness
$gt_4 = Producer::zip($gt_4, Producer::count_up(), 
	(Producer<int> $A, int $B) ==> $A->map((int $v) ==> sprintf('Is %d >4? %s', $v, $B ? 'Yes' : 'No'))) // identify the producers by their >4-ness
            ->flat_map(($I) ==> $I); // collapse back to one producer

// Get the last even value
$last_odd = (clone $root)->filter((int $v) ==> (bool) $v % 2)
                         ->last();
\HH\Asio\join(async {
	$all_gt_4 = await $gt_4->collapse(); // collapse to Vector of all generated items
	var_dump($all_gt_4);
	
	$last_odd = await $last_odd; // just await the single value
	var_dump($last_odd);
});