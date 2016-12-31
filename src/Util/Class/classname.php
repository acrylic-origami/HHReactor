<?hh // strict
namespace HHRx\Util\Class;
function classname(mixed $obj): string {
	$full = parse_classname(get_class($obj));
	return $full['classname'];
}