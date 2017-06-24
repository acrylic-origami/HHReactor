<?hh // strict
namespace HHReactor\Test;
use HHReactor\Producer;
use HHReactor\Appender;
class ConstructableProducer<+T> extends Producer<T> {
	public function __construct(Vector<(function(Appender<T>): AsyncIterator<T>)> $generator_factories) {
		parent::__construct($generator_factories);
	}
}