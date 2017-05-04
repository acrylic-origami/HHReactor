<?hh // strict
namespace HHReactor\HTTP\WebSocket;

use HHReactor\HTTP\Connection;
use HHReactor\HTTP\WebSocketConnection;

use GuzzleHttp\Psr7\Response;

class RFC6455 extends WebSocketConnection {
	private string $frame_buffer = '';
	
	const int CONT_FRAME_OPCODE = 0x00;
	const int TEXT_FRAME_OPCODE = 0x01;
	const int BIN_FRAME_OPCODE = 0x02;
	const int PING_FRAME_OPCODE = 0x09;
	const int PONG_FRAME_OPCODE = 0x0A;
	const int END_FRAME = 0x80800000; // FIN + Cont + Masked with zeroes, no body
	
	public function __construct(Connection<string> $tap) {
		parent::__construct($tap);
		
		$lowercase_headers = Map{};
		foreach($this->tap->get_request()->getHeaders() as $k => $header) 
			$lowercase_headers[strtolower($k)] = $header;
		
		$sec_websocket_accept = base64_encode(sha1(sprintf('%s%s', $lowercase_headers['sec-websocket-key'], self::GUID)));
		$response_status = \HH\Asio\join($this->tap->respond(new Response(
			101,
			[
				'Upgrade' => 'websocket',
				'Connection' => 'Upgrade',
				'Sec-Websocket-Accept' => $sec_websocket_accept
			]
		)));
		if(!$response_status)
			throw new \RuntimeException('Stream closed when trying to upgrade request to WebSocket');
	}
	
	public function _attach(): void {}
	
	public function close(): void {
		$this->tap->close();
	}
	
	public function respond(Response $response): Awaitable<bool> {
		return $this->tap->respond($response);
	}
	
	public function write(string $data): Awaitable<bool> {
		return $this->tap->write($data);
	}
	
	public async function send_frames(AsyncIterator<string> $frames, int $opcode = self::TEXT_FRAME_OPCODE): Awaitable<int> {
		$i = 0;
		foreach($frames await as $frame) {
			if($i > 0)
				$opcode = self::CONT_FRAME_OPCODE;
			
			$first_byte = 
				// 0b0 << 7 |
				// 0b000 << 4 |
				$opcode;
				
			if(strlen($frame) < 126)
				$payload_len = (1 << 7) & strlen($frame);
			elseif(strlen($frame) <= 0xFFFF)
				$payload_len = pack('Cn', 254, strlen($frame));
			else
				$payload_len = pack('CJ', 255, strlen($frame));
			
			$masking_key = pack('nn', mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF));
			
			$body = '';
			for($i = 0; $i < strlen($frame); $i++)
				$body .= ord($frame[$i]) ^ $masking_key[$i % 4];
			
			$write_status = await $this->write($first_byte . $payload_len . $masking_key . $body);
			$i += intval($write_status);
		}
		
		await $this->write(pack('J', self::END_FRAME));
		return $i;
	}
	
	public async function _produce(): Awaitable<?(mixed, Frame)> {
		$header = $this->frame_buffer;
		$this->frame_buffer = '';
		
		$header .= await $this->tap->get_bytes(4 - strlen($header)); // wait for first four bytes, which give fin, RSVs, opcodes, preliminary payload length and either part of the mask key, the extended payload length or part of the very extended payload length
		// if(strlen($header) < 4)
		// 	throw new \RuntimeException('Stream ended prematurely when building frame');
		
		$bytes = unpack('C2', substr($header, 0, 2));
		
		$fin = (bool)($bytes[1] & (1 << 7));
		$rsv = (new Vector(range(4, 6)))->map(($offset) ==> (bool)($bytes[1] & (1 << $offset))); // little endian RSV
		$opcode = $bytes[1] & 0x0F;
		$payload_len = $bytes[2] & ~(1 << 7); // assume string indexing like this gives me the second _byte_
		$mask_flag = (bool)($bytes[2] & (1 << 7));
		
		$mask_key = '';
		switch(127 - $payload_len) {
			case 0:
				// read for 64 bits of length
				$header .= await $this->tap->get_bytes(6 - strlen($header));
				// if(strlen($header) < 10)
				// 	throw new \RuntimeException('Stream ended prematurely when building frame');
				
				$payload_len = unpack('J', substr($header, 2, 8));
				$mask_key = substr($header, 10);
				break;
			case 1:
				// already have the extended length
				$payload_len = unpack('n', substr($header, 2, 2));
				$mask_key = substr($header, 4);
				break;
			default:
				// already have the shorter length (<125)
				$mask_key = substr($header, 2);
				break;
		}
		$mask_key .= await $this->tap->get_bytes(4 - strlen($mask_key));
		// if(strlen($header) < 4)
		// 	throw new \RuntimeException('Stream ended prematurely when building frame');
		
		$body = substr($mask_key, 4);
		$body .= await $this->tap->get_bytes($payload_len - strlen($body));
		
		// if(strlen($body) < $payload_len)
		// 	throw new \RuntimeException('Stream ended before sending full frame body');
		
		$this->frame_buffer = substr($body, $payload_len);
		return tuple(null, new Frame($fin, $opcode, substr($body, 0, $payload_len)));
	}
}