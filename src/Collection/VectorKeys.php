<?hh // strict
namespace HHRx\Collection;
class VectorKeys extends ConstVectorKeys implements ConsecutiveIterableIndexAccess<int> {
	public function add(int $v = 0): this {
		$this->count++;
		return $this;
	}
	<<__Deprecated('VectorKeys values are not settable (hint: there are no values).')>>
	public function setAll(?KeyedTraversable<int, int> $incoming): this {
		if(!is_null($incoming))
			foreach($incoming as $k => $_)
				if($k > $this->count)
					throw new \OutOfBoundsException(sprintf('Integer key %d is out of bounds', $k)); // throw standard Vector::setAll exception
		return $this;
	}
	<<__Deprecated('VectorKeys keys are not removable (hint: there are no keys).')>>
	public function removeKey(int $k): this {
		return $this;
	}
	<<__Deprecated('VectorKeys values are not settable (hint: there are no values).')>>
	public function set(int $k, int $_): this {
		if($k > $this->count)
			throw new \OutOfBoundsException(sprintf('Integer key %d is out of bounds', $k)); // throw standard Vector::set exception
		return $this;
	}
	
	public function clone(): VectorKeys {
		return new self($this->count);
	}
}