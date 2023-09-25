<?php

require_once dirname(__FILE__) . '/PacketProcessor.php';
require_once dirname(__FILE__) . '/Packet.php';

// Wraps a socket with EO packet processing functionality
class EOSocket
{
	private $socket; // Raw socket resource handle
	private $processor; // PacketProcessor instance for the connection
	protected $sendbuf = ''; // Pending send buffer
	protected $recvbuf = ''; // Pending recv buffer
	private $client = false; // True if the connection is a client

	public function __construct($socket = null, $client = false)
	{
		$this->processor = new PacketProcessor($client);
		$this->client = $client;

		if ($socket == null)
			$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		else
			$this->socket = $socket;
	}

	public function Connect($address, $port)
	{
		if (is_null($this->socket))
			return;

		return socket_connect($this->socket, $address, $port);
	}

	public function Bind($address, $port)
	{
		if (is_null($this->socket))
			return;

		socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);

		$res = socket_bind($this->socket, $address, $port);

		return $res;
	}

	public function Listen($backlog = 10)
	{
		if (is_null($this->socket))
			return;

		return socket_listen($this->socket, 1);
	}

	public function Accept()
	{
		if (is_null($this->socket))
			return null;

		return new EOSocket(socket_accept($this->socket), true);
	}

	public function Close()
	{
		if (is_null($this->socket))
			return;

		socket_close($this->socket);
		$this->recvbuf = "";
		$this->sendbuf = "";
		$this->socket = null;
	}

	public function IsClosed()
	{
		return is_null($this->socket);
	}

	public function GetPeerName()
	{
		if (is_null($this->socket))
			return null;

		$ip = false;
		socket_getpeername($this->socket, $ip);

		return $ip;
	}

	public function GetSocket()
	{
		return $this->socket;
	}

	public function Processor()
	{
		return $this->processor;
	}

	// Called when select() indicates the socket is ready to be read
	public function DoRecv()
	{
		if (is_null($this->socket))
			return 0;

		$read = socket_read($this->socket, 65536);

		if (strlen($read) == 0) {
			$this->Close();
			return 0;
		}

		$this->recvbuf .= $read;
		return strlen($read);
	}

	// Called when select() indicates the socket is ready to be written to, and sendbuf is not empty
	public function DoSend()
	{
		if (is_null($this->socket))
			return 0;

		$sent = socket_write($this->socket, $this->sendbuf);
		$this->sendbuf = substr($this->sendbuf, $sent);
		return $sent;
	}

	// True if there's data in the send buffer
	public function NeedSend()
	{
		return strlen($this->sendbuf) > 0;
	}

	// Add data to the packets send buffer to be sent during network pumping
	public function Send($data)
	{
		if (is_null($this->socket))
			return;

		$this->sendbuf .= $data;
	}

	// Attempt to extract a complete packet from the recv buffer
	// If a complete packet is not available, no data is extracted
	public function GetPacket()
	{
		if (strlen($this->recvbuf) < 2)
			return null;

		$length = Packet::Number(ord($this->recvbuf[0]), ord($this->recvbuf[1]));

		if (strlen($this->recvbuf) < $length + 2)
			return null;

		$rawdata = substr($this->recvbuf, 2, $length);
		$decdata = $this->processor->Decode($rawdata);
		$this->recvbuf = substr($this->recvbuf, $length + 2);

		$packet = new Packet($decdata, $this->client);

		if ($this->client)
			$this->processor->ScrapeClientPacket($packet);
		else
			$this->processor->ScrapeServerPacket($packet);

		return $packet;
	}

	// Encode and send a packet
	public function SendPacket($packet)
	{
		// Update the packet sequence byte, for packets being sent to the server
		if (!$this->client && !($packet->Family() == AC_INIT && $packet->Action() == OP_INIT))
			$packet->SetSequence($this->processor->GenSequence());
		else
			$this->processor->GenSequence();

		$rawdata = $packet->Serialize();

		$encdata = substr($rawdata, 0, 2) . $this->processor->Encode(substr($rawdata, 2));
		$this->Send($encdata);

		if (!$this->client)
			$this->processor->ScrapeClientPacket($packet);
		else
			$this->processor->ScrapeServerPacket($packet);
	}
}
