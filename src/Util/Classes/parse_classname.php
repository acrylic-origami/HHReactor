<?hh // strict
namespace HHRx\Util\Classes;
function parse_classname(string $name): FQClassname {
  return shape(
    'namespace' => array_slice(explode('\\', $name), 0, -1),
    'classname' => implode('', array_slice(explode('\\', $name), -1)),
  );
}