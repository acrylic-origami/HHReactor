<?hh // strict
function KeyedContainer_key_exists(KeyedContainer<mixed, mixed> $v, mixed $k): bool {
	try {
		$v[$k];
		return true;
	}
	catch(\OutOfBoundsException $e) {
		return false;
	}
}