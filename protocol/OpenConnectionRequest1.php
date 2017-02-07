<?php

/*
 * RakLib network library
 *
 *
 * This project is not affiliated with Jenkins Software LLC nor RakNet.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

namespace raklib\protocol;

#include <rules/RakLibPacket.h>


use raklib\RakLib;

class OpenConnectionRequest1 extends Packet{
	public static $ID = MessageIdentifiers::ID_OPEN_CONNECTION_REQUEST_1;

	public $protocol = RakLib::PROTOCOL;
	public $mtuSize;

	public function encode(){
		parent::encode();
		$this->put(RakLib::MAGIC);
		$this->putByte($this->protocol);
		str_pad($this->buffer, $this->mtuSize - 20 - 8, "\x00");
	}

	public function decode(){
		parent::decode();
		$this->offset += 16; //Magic
		$this->protocol = $this->getByte();
		$this->mtuSize = strlen($this->buffer) + 20 + 8; //IP header size + UDP header size + total buffer size
	}
}