<?hh // decl
namespace HHRx\Util;
class Classes {
	public static function parse_classname($name) {
	  return array(
	    'namespace' => array_slice(explode('\\', $name), 0, -1),
	    'classname' => join('', array_slice(explode('\\', $name), -1)),
	  );
	}
	public static function classname($name) {
		$full = self::parse_classname($name);
		return $full['classname'];
	}
}