<?hh // strict
namespace HHReactor\Util\Classes;
function classname(mixed $obj): string {
	$full = parse_classname(get_class($obj));
	return $full['classname'];
}