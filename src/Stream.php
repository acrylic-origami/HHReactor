<?hh // strict
namespace HHRx;
use HHRx\Util\Collection\KeyedContainerWrapper as KC;
use HHRx\Util\Collection\AsyncKeyedContainerWrapper as AsyncKC;
class Stream<+T> extends KeyedStream<mixed, T> {}