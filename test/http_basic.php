<?hh // partial
namespace HHReactor\Test;
require_once __DIR__ . '/../vendor/hh_autoload.php';
use HHReactor\HTTP\ConnectionIterator;
/* HH_IGNORE_ERROR[1002] */
\HH\Asio\join(async {
	$iterator = new ConnectionIterator(8080);
	foreach($iterator await as $connection) {
		$header = $connection->get_request();
		var_dump($header);
		$body = '';
		foreach($connection await as $buf)
			$body .= $buf;
		var_dump($body);
		await $connection->write("HTTP/1.1 200 OK\r\n\r\n");
		echo 'RESPONDED!';
	}
});