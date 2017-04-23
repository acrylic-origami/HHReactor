<?hh // strict
namespace HHReactor\Test\Classwise\Producer;
use HHReactor\Collection\Producer;
type ProducerVector<+T> = shape('sut' => Producer<T>, 'expected' => \ConstVector<T>);