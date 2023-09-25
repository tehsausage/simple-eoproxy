<?php

require_once dirname(__FILE__) . '/PacketDefs.php';

// EO integer encoding constants
define('PACKET_MAX1', 253);
define('PACKET_MAX2', 64009);
define('PACKET_MAX3', 16194277);
define('PACKET_MAX4', 4097152081);

// Stream-like reader/writer for packets
class Packet
{
	private $id;   // Array of two numbers
	private $seq;  // Array of one number (or empty array)
	private $data; // Array of packet data bytes
	private $pos;  // Current read/write position
	private $has_seq; // True if the packet contains a sequence byte (from the client)

	// -- Static functions --

	// Decode bytes in to an integer
	public static function Number($bit1 = 0, $bit2 = 254, $bit3 = 254, $bit4 = 254, $bit5 = 254)
	{
		if ($bit1 == 0 || $bit1 == 254) $bit1 = 1;
		if ($bit2 == 0 || $bit2 == 254) $bit2 = 1;
		if ($bit3 == 0 || $bit3 == 254) $bit3 = 1;
		if ($bit4 == 0 || $bit4 == 254) $bit4 = 1;
		if ($bit5 == 0 || $bit5 == 254) $bit5 = 1;

		--$bit1;
		--$bit2;
		--$bit3;
		--$bit4;
		--$bit5;

		return ($bit5*PACKET_MAX4 + $bit4*PACKET_MAX3 + $bit3*PACKET_MAX2 + $bit2*PACKET_MAX1 + $bit1);
	}

	// Encode a number in to an array of bytes
	public static function ENumber($number, $size)
	{
		$max = [PACKET_MAX1, PACKET_MAX2, PACKET_MAX3, PACKET_MAX4];
		$b = array_fill(0, $size, 254);

		for ($i = 4; $i >= 1; --$i)
		{
			if ($i >= $size)
			{
				if ($number >= $max[$i - 1])
					$number = $number % $max[$i - 1];
			}
			else if ($number >= $max[$i - 1])
			{
				$b[$i] = (floor($number / $max[$i - 1]) + 1) & 0xFF;
				$number = $number % $max[$i - 1];
			}
			else
			{
				$b[$i] = 254;
			}
		}

		$b[0] = ($number + 1) & 0xFF;

		return $b;
	}

	// Shortcuts to encode numbers in to various data sizes
	public static function EChar($number) { return self::ENumber($number, 1); }
	public static function EShort($number) { return self::ENumber($number, 2); }
	public static function EThree($number) { return self::ENumber($number, 3); }
	public static function EInt($number) { return self::ENumber($number, 4); }
	public static function EFive($number) { return self::ENumber($number, 5); }

	// -- End static functions --

	// Create a Packet instance, optionally from an array of decoded packet data
	public function __construct($b = null, $from_client = false)
	{
		$this->has_seq = false;
		$this->pos = 0;
		$this->seq = "";

		if (!is_null($b))
		{
			$this->id = substr($b, 0, 2);

			if ($from_client && !($b[0] == chr(255) && $b[1] == chr(255)))
			{
				$this->has_seq = true;
				$this->seq = $b[2];
				$this->data = substr($b, 3);
			}
			else
			{
				$this->data = substr($b, 2);
			}
		}
		else
		{
			$this->id = chr(255) . chr(255);
			if ($from_client)
			{
				$this->has_seq = true;
				$this->seq = chr(255);
			}
			$this->data = "";
		}
	}

	public function Family()
	{
		return ord($this->id[1]);
	}

	public function Action()
	{
		return ord($this->id[0]);
	}

	public function Length()
	{
		return strlen($this->data);
	}

	public function Sequence()
	{
		if ($this->has_seq)
			return Packet::Number(ord($this->seq[0]));
		else
			return false;
	}

	public function SetSequence($seq)
	{
		$n = Packet::ENumber($seq, 1);

		if ($this->has_seq)
		{
			$this->seq[0] = chr($n[0]);
		}
	}

	public function Data()
	{
		return $this->data;
	}

	// Convert the Packet instance in to an encapsulatead array of packet data bytes
	public function Serialize()
	{
		$payload = $this->id . $this->seq . $this->data;
		return implode('', array_map('chr', Packet::ENumber(strlen($payload), 2))) . $payload;
	}

	// Set the read/write pos in the packet
	public function Seek($pos)
	{
		$this->pos = intval($pos);
	}

	// Offset the read/write pos in the packet
	public function Skip($n)
	{
		$this->pos += $n;
	}

	// Clear all data in a packet, but keep ID and sequence number
	public function Reset()
	{
		$this->data = "";
		$this->pos = 0;
	}

	// Change the packet ID
	public function SetID($family, $action)
	{
		$this->id[0] = chr($action);
		$this->id[1] = chr($family);
	}

	// Read a number at the given
	private function GetNumber($n)
	{
		$params = array_map('ord', str_split(substr($this->data, $this->pos, $n)));
		$this->pos += $n;
		return call_user_func_array('Packet::Number', $params);
	}

	// Read a raw byte without advancing the read/write position
	public function PeekByte()
	{
		if (isset($this->data[$this->pos]))
			return ord($this->data[$this->pos]);
		else
			return 0;
	}

	// Number of bytes remaining before reaching the end of the packet
	public function Remaining()
	{
		return max(0, strlen($this->data) - $this->pos);
	}

	// Read a raw byte value from the packet
	public function GetByte()
	{
		if (isset($this->data[$this->pos]))
			return ord($this->data[$this->pos++]);
		else
			return 0;
	}

	// Read functions for standard integer sizes
	public function GetChar() { return $this->GetNumber(1); }
	public function GetShort() { return $this->GetNumber(2); }
	public function GetThree() { return $this->GetNumber(3); }
	public function GetInt() { return $this->GetNumber(4); }
	public function GetFive() { return $this->GetNumber(5); }

	// Read a fixed-length string from a packet
	public function GetString($n)
	{
		$result = substr($this->data, $this->pos, $n);
		$this->pos += $n;
		return $result;
	}

	// Read an 0xFF-byte terminated string from a packet
	public function GetBreakString()
	{
		$result = "";
		$start = $this->pos;

		while ($this->pos < strlen($this->data) && $this->data[$this->pos] != chr(255))
		{
			$result .= $this->data[$this->pos];
			++$this->pos;
		}

		++$this->pos;

		return $result;
	}

	// Seek the packet forward until past the the next 0xFF byte
	public function NextChunk()
	{
		while ($this->pos < strlen($this->data) && $this->data[$this->pos] != chr(255))
			++$this->pos;

		++$this->pos;
	}

	// Return the remaining packet data as a string
	public function GetEndString()
	{
		$result = "";
		$start = $this->pos;

		while ($this->pos < strlen($this->data))
		{
			$result .= $this->data[$this->pos];
			++$this->pos;
		}

		return $result;
	}

	// Add a raw byte value to the packet
	public function AddByte($x)
	{
		if (isset($this->data[$this->pos]))
			$this->data[$this->pos++] = chr($x);
		else
			{ $this->data .= chr($x); ++$this->pos; }

		return $x;
	}

	// Encode an integer and add it to the packet
	public function AddNumber($x, $n)
	{
		$b = Packet::ENumber($x, $n);

		for ($i = 0; $i < $n; ++$i)
		{
			$this->AddByte($b[$i]);
		}

		return $x;
	}

	// Add functions for standard integer sizes
	public function AddChar($x) { return $this->AddNumber($x, 1); }
	public function AddShort($x) { return $this->AddNumber($x, 2); }
	public function AddThree($x) { return $this->AddNumber($x, 3); }
	public function AddInt($x) { return $this->AddNumber($x, 4); }
	public function AddFive($x) { return $this->AddNumber($x, 5); }

	// Add a raw string to the packet
	public function AddString($x)
	{
		for ($i = 0; $i < strlen($x); ++$i)
		{
			$this->AddByte(ord($x[$i]));
		}

		return $x;
	}

	// Add a string terminated by an 0xFF byte
	public function AddBreakString($x)
	{
		for ($i = 0; $i < strlen($x); ++$i)
		{
			$this->AddByte(ord($x[$i]));
		}

		$this->AddByte(255);

		return $x;
	}
}

