<?hh // strict
namespace HHRx\Collection;
<<__ConsistentConstruct>>
interface EmptyKeyedContainerFactory<+Tk as arraykey, +Tv, +TContainer as ?KeyedContainer<Tk, Tv>> {
	public function __construct();
	abstract public function make_container(): TContainer;
}