<?hh // strict
namespace HHRx\Collection;
class IterableIndexAccess<Tk as arraykey, Tv, +TCollection as ?\IndexAccess<Tk, Tv>> extends IterableConstIndexAccess<Tk, Tv, TCollection> implements \IndexAccess<Tk, Tv> {
	public function set(Tk $k, Tv $v): this {
		$this->keys->add($k);
		$units = $this->get_units();
		invariant(!is_null($units), 'Cannot `set` on null collection.');
		$units->set($k, $v);
		return $this;
	}
	public function setAll(?KeyedTraversable<Tk, Tv> $incoming): this {
		// no unique keys: no need to modify $this->keys;
		if(!is_null($incoming)) {
			$units = $this->get_units();
			invariant(!is_null($units), 'Cannot `setAll` on null collection.');
			$units->setAll($incoming);
		}
		return $this;
	}
	public function removeKey(Tk $k): this {
		// $this->keys->remove($k);
		$units = $this->get_units();
		invariant(!is_null($units), 'Cannot `removeKey` from null collection.');
		$units->removeKey($k);
		return $this;
	}
}