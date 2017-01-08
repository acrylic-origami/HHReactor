<?hh // strict
namespace HHRx\Collection;
trait FulfillMutableKeyedContainerWrapper<Tk, Tv, +TCollection as \MutableKeyedContainer<Tk, Tv>> {
	require extends KeyedContainerWrapper<Tk, Tv, TCollection>;
	public function get(Tk $k): ?Tv {
		$units = $this->get_units();
		invariant(!is_null($units), '');
		return $units->get($k);
	}
	public function at(Tk $k): Tv {
		return $this->get_units()->at($k);
	}
	public function containsKey<Tu super Tk>(Tu $k): bool {
		return $this->get_units()->containsKey($k);
	}
	public function set(Tk $k, Tv $v): this {
		$this->get_units()->set($k, $v);
		return $this;
	}
	public function setAll(?KeyedTraversable<Tk, Tv> $incoming): this {
		$this->get_units()->setAll($incoming);
		return $this;
	}
	public function removeKey(Tk $k): this {
		$this->get_units()->removeKey($k);
		return $this;
	}
	public function slice(int $start, int $len): this {
		$ret = new static();
		$iterator = $this->getIterator();
		for(; $len > 0 && $iterator->valid(); (($start-- > 0) ?: $len--) && $iterator->next())
			$ret->set($iterator->key(), $iterator->current());
		return $ret;
	}
	public function takeWhile((function(Tv): bool) $until): this {
		$ret = new static();
		for($iterator = $this->getIterator(); $until($iterator->current()); $iterator->next()) {
			$ret->set($iterator->key(), $iterator->current());
		}
		return $ret;
	}
	public function filter((function(Tv): bool) $fn): this {
		$ret = new static();
		foreach($this->getIterator() as $k => $v)
			if($fn($v))
				$ret->set($k, $v);
		return $ret;
	}
	public function filterWithKey((function(Tk, Tv): bool) $fn): this {
		$ret = new static();
		foreach($this->getIterator() as $k => $v)
			if($fn($k, $v))
				$ret->set($k, $v);
		return $ret;
	}
	public function map<Tu>((function(Tv): Tu) $fn): KeyedContainerWrapper<Tk, Tu, \MutableKeyedContainer<Tk, Tu>> {
		$ret = new static();
		foreach($this->getIterator() as $k => $v)
			$ret->set($k, $fn($v));
		return $ret;
	}
	public function mapWithKey<Tu>((function(Tk, Tv): Tu) $fn): KeyedContainerWrapper<Tk, Tu, \MutableKeyedContainer<Tk, Tu>> {
		$ret = new static();
		foreach($this->getIterator() as $k => $v)
			$ret->set($k, $fn($k, $v));
		return $ret;
	}
}