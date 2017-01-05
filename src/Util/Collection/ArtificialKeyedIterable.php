<?hh // strict
namespace HHRx\Util\Collection;
use HHRx\Util\Collection\KeyedContainerWrapper as KC;
abstract class ArtificialKeyedIterable<Tk, +Tv> extends ArtificialCovKeyedIterable<Tk, Tv, KC<Tk, Tv>> implements KeyedIterable<Tk, Tv> {
	public function __construct() {
		parent::__construct(KC::class);
	}
	abstract public function getIterator(): KeyedIterator<Tk, Tv>;
	public function toImmMap(): ImmMap<Tk, Tv> {
		return new ImmMap($this->toMap());
	}
	<<__Override>>
	public function map<Tu>((function(Tv): Tu) $fn): KC<Tk, Tu> {
		$M = Map{};
		foreach($this->getIterator() as $k=>$unit)
			$M[$k] = $fn($unit);
		return new KC($M);
	}
	<<__Override>>
	public function mapWithKey<Tu>((function(Tk, Tv): Tu) $fn): KC<Tk, Tu> {
		$M = Map{};
		foreach($this->getIterator() as $k=>$unit)
			$M[$k] = $fn($k, $unit);
		return new KC($M);
	}
}