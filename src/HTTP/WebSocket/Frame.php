<?hh // strict
namespace HHReactor\HTTP\WebSocket;
class Frame implements \Stringish {
	public function __construct(private bool $fin, private int $opcode, private string $body) {}
	public function __toString(): string {
		return $this->body;
	}
}