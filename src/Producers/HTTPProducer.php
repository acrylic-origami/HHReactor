<?hh // strict
namespace HHReactor\Producer;
use HHReactor\Collection\Producer;

function HTTPProducer(private int $port, private string $host = '127.0.0.1'): Producer<string> {
	$server = stream_socket_server(sprintf('tcp://%s:%d', $host, $port));
	stream_set_blocking($server, false);
	return new Producer(async {
		do {
			printf("Construct on port %d\n", $port);
			$status = await stream_await($server, STREAM_AWAIT_READ, 0.0);
			printf("Recv on port %d\n", $port);
			if($status === STREAM_AWAIT_READY) {
				$conn = stream_socket_accept($server, 0.0);
				stream_set_blocking($conn, false);
				yield stream_get_contents($conn);
			}
		}
		while($status === STREAM_AWAIT_READY);
	});
}