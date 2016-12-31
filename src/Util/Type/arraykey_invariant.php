<?hh // strict
function arraykey_invariant(mixed $v, string $err_msg, (function(arraykey): void) $then): void {
	if(is_string($v))
		$then($v);
	elseif(is_int($v))
		$then($v);
	else
		invariant(false, $err_msg);
}