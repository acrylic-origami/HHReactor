<?hh // strict
namespace HHReactor\Asio;
type HaltResult<+T> = shape('_halted' => bool, 'result' => ?T);