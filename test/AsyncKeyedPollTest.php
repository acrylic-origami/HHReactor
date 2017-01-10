<?hh // partial
require_once(__DIR__ . '/../vendor/autoload.php');

async function wait(int $usecs): Awaitable<void> {
	await HH\Asio\usleep($usecs);
	// echo $usecs."\n";
}
function make_wait_stream(Map<int, int> $wait_times): HHRx\AsyncKeyedPoll<mixed, mixed> {
	return new HHRx\AsyncKeyedPoll(new HHRx\Collection\MapW($wait_times->map((int $usecs) ==> wait($usecs))));
}

HH\Asio\join(async {
	foreach(make_wait_stream(Map{ 0 => 1, 1 => 600000, 2 => 300000, 3 => 1000000 }) await as $next) {
		// var_dump($next);
	}
});