<?hh // strict
namespace HHReactor\Test\Classwise\Producer;
use HHReactor\Producer;
type ProducerVector<+T> = shape('sut' => Producer<T>, 'expected' => \ConstVector<T>);