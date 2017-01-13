<?hh // strict
namespace HHRx\Util\Classes;
function classname(mixed $obj): string {
	$full = parse_classname(get_class($obj));
	return $full['classname'];
}