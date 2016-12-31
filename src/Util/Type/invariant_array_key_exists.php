<?hh // strict
/* HH_FIXME[4033] Typed variadic arguments not yet supported by HHVM */
function invariant_array_key_exists<Tk as arraykey, Tv, Tr>(array<Tk, Tv> $A, string $msg, ...$keys): Tr {
	foreach($keys as $key) {
		invariant(is_string($key) || is_int($key), 'Only `arraykeys` can be used as array keys.');
		invariant(is_array($A), $msg);
		invariant(array_key_exists($key, $A), $msg);
		$A = $A[$key];
	}
	return $A;
}