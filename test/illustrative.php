<?hh // partial
use HHRx\StreamFactory;
require_once __DIR__ . '/../vendor/hh_autoload.php';
$factory = new StreamFactory();
$delays = Vector{ 300, 400, 1000 }; // delays in ms
$streams = $delays->map((int $delay) ==> $factory->tick($delay * 1000)); // convert to us

// NOTE the factory performing the merge
$river = $factory->merge($streams);
                 // ->map((int $delay) ==> sprintf('Waited %d us', $delay)); // transform emitted values
// ... but the stream performing `end_on`,
// since it mutates the stream.
// $river->end_on(\HH\Asio\usleep(10 * 1000000)); // 10s

// NOTE the async handler, which is that 
// way for generality (and for some 
// internal behavior).
$river->subscribe(
    async (string $delay_msg) ==> var_dump($delay_msg)
);

// Kick off the application
HH\Asio\join($factory->get_total_awaitable());