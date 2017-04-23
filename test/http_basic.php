<?hh // partial
namespace HHReactor\Test;
require_once __DIR__ . '/../vendor/hh_autoload.php';
use HHReactor\HTTP\ConnectionIterator;
/* HH_IGNORE_ERROR[1002] */
\HH\Asio\join(async {
	$iterator = new ConnectionIterator(1337);
	foreach($iterator await as $request) {
		var_dump($request[0]);
		foreach($request[1] await as $buf)
			var_dump($buf);
	}
});