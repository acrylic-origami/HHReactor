<?hh
require_once(dirname(__DIR__) . '/vendor/autoload.php');
$L = new HHRx\Collection\LinkedList(Vector{1, 3, 5, 7});
while(!$L->is_empty())
	var_dump($L->shift());
