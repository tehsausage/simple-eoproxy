<?php

require_once dirname(__FILE__) . '/PacketDefs.php';
require_once dirname(__FILE__) . '/Packet.php';

// Tracks and applies packet encryption for an EOSocket
class PacketProcessor
{
	private $is_client; // True if we are a client socket, False for server socket

	// Packet encryption state
	private $challenge = -1;
	private $server_encval = -1;
	private $client_encval = -1;

	// Track the sequence number for packets ourselves, so blocking/injecting packets can work
	private $seq_start = -1;
	private $seq = 0;

	// Algorithm to derive the sequnce start value from the init packet
	private function InitSequence($s1, $s2)
	{
		$this->seq_start = $s1 * 7 + $s2 - 13;
	}

	// Algorithm to derive the sequence start value from the ping packet
	private function UpdateSequence($s1, $s2)
	{
		$this->seq_start = max($s1 - $s2, 0);
	}

	// Generate the next sequence value that should appear in a packet
	public function GenSequence()
	{
		// Accurately simulate integer overflow for eoserv anti-bot trap
		if ($this->seq_start + $this->seq < 0)
			$result = 81 + $this->seq_start + $this->seq;
		else
			$result = $this->seq_start + $this->seq;

		$this->seq = ($this->seq + 1) % 10;

		return $result % 253;
	}

	public function __construct($client)
	{
		$this->is_client = $client;
	}

	public function HasEncryption()
	{
		return $this->server_encval >= 0 && $this->client_encval >= 0;
	}

	public function RememberChallenge($challenge)
	{
		$this->challenge = $challenge;
	}

	public function SetupEncryptionFromInit($server_encval, $client_encval)
	{
		if ($this->challenge < 0)
			throw new Exception("RememberChallenge was not called before SetupEncryptionFromInit");
		$this->server_encval = $server_encval;
		$this->client_encval = $client_encval += $this->challenge % 11;
	}

	public function UpdateEncryptionFromClient($n)
	{
		$this->client_encval += $n;
	}

	public function UpdateEncryptionFromServer($n)
	{
		$this->server_encval += $n + $this->client_encval % 50;
	}

	// Extract encryption-related parameters from client-origin packets
	public function ScrapeClientPacket($packet)
	{
		if (!$this->HasEncryption() && $packet->Family() == OP_INIT && $packet->Action() == AC_INIT)
		{
			$challenge = $packet->GetThree();
			$this->RememberChallenge($challenge);
		}
		else if ($this->HasEncryption() && $packet->Family() == OP_PLAY && $packet->Action() == AC_REQUEST)
		{
			$packet->GetInt(); // char_id
			$n = $packet->GetChar();
			$this->UpdateEncryptionFromClient($n);
		}
		$packet->Seek(0);
	}

	// Extract encryption-related parameters from server-origin packets
	public function ScrapeServerPacket($packet)
	{
		if (!$this->HasEncryption() && $packet->Family() == OP_INIT && $packet->Action() == AC_INIT)
		{
			$init_reply = $packet->GetByte();

			if ($init_reply == 2) // INIT_OK
			{
				$seq1 = $packet->GetByte();
				$seq2 = $packet->GetByte();
				$server_encval = $packet->GetShort();
				$client_encval = $packet->GetShort();
				$this->InitSequence($seq1, $seq2);
				$this->SetupEncryptionFromInit($server_encval, $client_encval);
			}
		}
		else if ($this->HasEncryption() && $packet->Family() == OP_SECURITY && $packet->Action() == AC_SET)
		{
			$seq1 = $packet->GetShort();
			$seq2 = $packet->GetChar();
			$this->UpdateSequence($seq1, $seq2);
		}
		else if ($this->HasEncryption() && $packet->Family() == OP_PLAY && $packet->Action() == AC_CONFIRM)
		{
			$n = $packet->GetChar();
			$this->UpdateEncryptionFromServer($n);
		}
		$packet->Seek(0);
	}

	// Encrypt packet data bytes
	public function Encode($b)
	{
		if (ord($b[0]) == OP_INIT && ord($b[1]) == AC_INIT)
			return $b;

		if (!$this->HasEncryption())
			throw new Exception("Encryption parameters not set");

		$encval = $this->is_client ? $this->server_encval : $this->client_encval;

		$enckey_table = [
			fn ($i) => -($i + 0x74),
			fn ($i) => +floor($encval / 253),
			fn ($i) => -(($encval - 1) % 253),
		];

		// Intentionally ignores the last byte of the packet to match EO behavior
		for ($i = 1; $i < strlen($b); ++$i)
		{
			$val = ord($b[$i - 1]);
			$val = ($val + $enckey_table[$i % 3]($i)) & 0xFF;
			$b[$i - 1] = chr($val);
		}

		return $b;
	}

	// Decrypt packet data bytes
	public function Decode($b)
	{
		if (ord($b[0]) == OP_INIT && ord($b[1]) == AC_INIT)
			return $b;

		if (!$this->HasEncryption())
			throw new Exception("Encryption parameters not set");

		$decval = $this->is_client ? $this->client_encval : $this->server_encval;

		$deckey_table = [
			fn ($i) => +($i + 0x74),
			fn ($i) => -floor($decval / 253),
			fn ($i) => +(($decval - 1) % 253),
		];

		// Intentionally ignores the last byte of the packet to match EO behavior
		for ($i = 1; $i < strlen($b); ++$i)
		{
			$val = ord($b[$i - 1]);
			$val = ($val + $deckey_table[$i % 3]($i)) & 0xFF;
			$b[$i - 1] = chr($val);
		}

		return $b;
	}
}
