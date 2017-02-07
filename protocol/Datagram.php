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

class Datagram extends Packet{

	const BITFLAG_VALID = 0x80;
	const BITFLAG_ACK = 0x40;
	const BITFLAG_NAK = 0x20;
	const BITFLAG_PACKET_PAIR = 0x10;
	const BITFLAG_CONTINUOUS_SEND = 0x08;
	const BITFLAG_NEEDS_B_AND_AS = 0x04;
	/*
	 * isValid          0x80
	 * isACK            0x40
	 * isNAK            0x20 (hasBAndAS for ACKs)
	 * isPacketPair     0x10
	 * isContinuousSend 0x08
	 * needsBAndAS      0x04
	 */

	/** @var EncapsulatedPacket[] */
	public $packets = [];

	public $seqNumber;

	public function encode(){
		parent::encode();
		$this->putLTriad($this->seqNumber);
		foreach($this->packets as $packet){
			$this->put($packet instanceof EncapsulatedPacket ? $packet->toBinary() : (string) $packet);
		}
	}

	public function length(){
		$length = 4;
		foreach($this->packets as $packet){
			$length += $packet instanceof EncapsulatedPacket ? $packet->getTotalLength() : strlen($packet);
		}

		return $length;
	}

	public function decode(){
		parent::decode();
		$this->seqNumber = $this->getLTriad();

		while(!$this->feof()){
			$offset = 0;
			$data = substr($this->buffer, $this->offset);
			$packet = EncapsulatedPacket::fromBinary($data, false, $offset);
			$this->offset += $offset;
			if(strlen($packet->buffer) === 0){
				break;
			}
			$this->packets[] = $packet;
		}
	}

	public function clean(){
		$this->packets = [];
		$this->seqNumber = null;
		return parent::clean();
	}
}