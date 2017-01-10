<?hh // partial
require_once(__DIR__ . '/../vendor/autoload.php');

async function wait(int $usecs): Awaitable<int> {
	await HH\Asio\usleep($usecs);
	return $usecs;
}
function make_wait_stream(Map<int, int> $wait_times): HHRx\AsyncKeyedPoll<mixed, mixed> {
	return new HHRx\AsyncKeyedPoll(new HHRx\Collection\MapW($wait_times->map((int $usecs) ==> wait($usecs))));
}

$merged = HHRx\AsyncKeyedPoll::merge((Vector{ Map{ 0 => 1, 1 => 600000, 2 => 300000, 3 => 1000000 }, Map{ 4 => 450000, 5 => 150000, 6 => 750000 } })->map((Map<int, int> $wait_times) ==> make_wait_stream($wait_times)));
HH\Asio\join(async {
	foreach($merged await as $next) {
		var_dump($next);
	}
});