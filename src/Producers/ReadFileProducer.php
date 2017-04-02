<?hh // strict
namespace HHReactor\Producer;
use HHReactor\Collection\Producer;
function ReadFileProducer(string $f, bool $write_en = false, float $timeout = 0.0): Producer<string> {
	$handle = fopen($f, 'r'.($write_en ? '+' : ''));
	return Producer::create(async {
		do {
			$status = await stream_await($handle, STREAM_AWAIT_READ, $timeout);
			do {
				$line = fgets($handle);
				yield $line;
			}
			while($line !== "\x04"); // is not EOF
		}
		while($status === STREAM_AWAIT_READY);
	});
}