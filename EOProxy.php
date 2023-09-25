<?php

require_once dirname(__FILE__) . '/EOSocket.php';
require_once dirname(__FILE__) . '/PacketProcessor.php';
require_once dirname(__FILE__) . '/Packet.php';

class EOProxy
{
	protected $server = null; // EOSocket instance
	protected $client = null; // EOSocket instance

	// Get Server EOSocket
	public function Server()
	{
		return $this->server;
	}

	// Get Client EOSocket
	public function Client()
	{
		return $this->client;
	}

	// Can be called by scripts to "inject" an EOPacket to the client
	public function SendToClient($packet)
	{
		$packet->Seek(0);
		$this->PresentPacket($packet, false);
		$this->server->ScrapeServerPacket($packet);
		$this->client->SendPacket($packet);
	}

	// Can be called by scripts to "inject" an EOPacket to the server
	public function SendToServer($packet)
	{
		$packet->Seek(0);
		$this->PresentPacket($packet, true);
		$this->client->ScrapeClientPacket($packet);
		$this->server->SendPacket($packet);
	}

	// Converts packet IDs in to a friendly name
	public function PacketName($family, $action)
	{
		// ID->name functions defined in Packet.php
		return packet_family_name($family) . '_' . packet_action_name($action);
	}

	private function PresentPacket($packet, $client)
	{
		// Print packet header
		echo $client ? "[C->S] " : "[S->C] ";
		echo $this->PacketName($packet->Family(), $packet->Action());
		echo " (length=" . $packet->Length() . ")";

		// Print raw packet byte dump
		echo "\n";
		echo implode(' ', array_map(function($c)
		{
			return sprintf("%02X", ord($c));
		}, str_split($packet->Data())));
		echo "\n";
	}

	// Waits for a single client connection
	// Once connected, initiates a server connection as well
	protected function WaitForConnect($local_address, $local_port, $remote_address, $remote_port)
	{
		$listener = new EOSocket();

		if (!$listener->Bind($local_address, $local_port))
			exit("Failed to listen on $local_address:$local_port\n");

		if (!$listener->Listen(1))
			exit("Failed to listen on $local_address:$local_port\n");

		echo "Listening on $local_address:$local_port . . .\n";

		$this->client = $client = $listener->Accept();
		echo "Client accepted: ", $client->GetPeerName(), "\n";
		$listener->Close();

		$this->server = $server = new EOSocket();

		echo "Connecting to server: $remote_address:$remote_port . . .\n";

		if (!$server->Connect($remote_address, $remote_port))
		{
			$client->Close();
			exit("Failed to connect to $remote_address:$remote_port\n");
		}

		echo "Connected!\n";
	}

	// Process networking on the connected sockets, blocking until a network event occurs
	protected function PumpNetworking()
	{
		$client_sock = $this->client->GetSocket();
		$server_sock = $this->server->GetSocket();

		$read = [];
		$write = [];
		$except = [];

		if (!$this->client->IsClosed())
			$read[] = $client_sock;

		if (!$this->server->IsClosed())
			$read[] = $server_sock;

		if ($this->client->NeedSend())
		{
			$write[] = $client_sock;
		}
		else
		{
			// If the server disconnected, then disconnect the client as well once all server data is sent
			if ($this->server->IsClosed() && !$this->client->IsClosed())
				$this->client->Close();
		}

		if ($this->server->NeedSend())
		{
			$write[] = $server_sock;
		}
		else
		{
			// If the client disconnected, then disconnect from the server as well once all client data is sent
			if ($this->client->IsClosed() && !$this->server->IsClosed())
				$this->server->Close();
		}

		// Once the server and client are both disconnected, return false to trigger EOProxy to exit
		if (empty($read) && empty($write) && empty($except))
		{
			echo "Finished.\n";
			return false;
		}

		$threw = false;
		try
		{
			if (socket_select($read, $write, $except, null) === false)
				echo "select() failed.\n";
		}
		catch (TypeError $e)
		{
			// Throws when attempting to select() on a closed socket handle
			$threw = true;
			echo "select() threw.\n";
		}

		// If select throws then disconnect and let everything end itself cleanly
		if ($threw && !$this->server->IsClosed())
			$this->server->Close();

		if (in_array($client_sock, $read) && !$this->client->IsClosed())
		{
			if ($this->client->DoRecv() == 0) {
				echo("Client disconnected.\n");
				$this->client->Close();
			}
		}

		if (in_array($server_sock, $read) && !$this->server->IsClosed())
		{
			if ($this->server->DoRecv() == 0) {
				echo("Server disconnected.\n");
				$this->server->Close();
			}
		}

		if (in_array($client_sock, $write))
			$this->client->DoSend();

		if (in_array($server_sock, $write))
			$this->server->DoSend();

		return true;
	}

	// Accept one client connection, run until completion and then return
	public function RunProxy($local_address, $local_port, $remote_address, $remote_port)
	{
		$this->WaitForConnect($local_address, $local_port, $remote_address, $remote_port);

		while (true)
		{
			if (!$this->PumpNetworking())
				break;

			// Attempt to read a full packet from the client-socket recv buffer, and forward it to the server
			while (!is_null($packet = $this->client->GetPacket(true)))
			{
				$this->PresentPacket($packet, true);
				$this->server->SendPacket($packet);
			}

			// Attempt to read a full packet from the server-socket recv buffer, and forward it to the client
			while (!is_null($packet = $this->server->GetPacket(true)))
			{
				$this->PresentPacket($packet, false);
				$this->client->SendPacket($packet);
			}
		}
	}
}
