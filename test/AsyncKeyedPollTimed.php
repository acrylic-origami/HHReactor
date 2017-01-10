<?hh
require_once(__DIR__ . '/../vendor/autoload.php');
async function waitt(int $i): Awaitable<int> {
	await HH\Asio\usleep($i);
	return $i;
}
$times = Map{};
HH\Asio\join(async {
	$range = range(1, 1000);
	shuffle($range);
	$gen = new HHRx\AsyncKeyedPoll((new Vector($range))->map((int $i) ==> waitt($i*100)));
	foreach($gen await as $v) {
		$times->set($v, microtime());
	}
});
$ret = '';
foreach($times->mapWithKey((int $k, string $time) ==> {
	list($usec, $sec) = explode(' ', $time);
	return sprintf('%d,%s.%s', $k, $sec, ltrim($usec, '0.'));
}) as $row)
	$ret .= $row."\n";
echo $ret;