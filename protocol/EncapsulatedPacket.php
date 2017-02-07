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

#ifndef COMPILE
use raklib\Binary;

#endif

#include <rules/RakLibPacket.h>

class EncapsulatedPacket{

	/*
	 * 1  (bitflags)
	 * 2  (data length)
	 * 3  (message index)
	 * 4  (3 (sequence number) + 1 (order channel))
	 * 10 (4 (split count) + 2 (split id) + 4 (split index))
	 */
	const MAX_HEADER_LENGTH = 20;

	public $reliability;
	public $hasSplit = false;
	public $length = 0;
	public $messageIndex = null;
	public $orderIndex = null;
	public $orderChannel = null;
	public $splitCount = null;
	public $splitID = null;
	public $splitIndex = null;
	public $buffer;
	public $needACK = false;
	public $identifierACK = null;

	/**
	 * @param string $binary
	 * @param bool   $internal
	 * @param int    &$offset
	 *
	 * @return EncapsulatedPacket
	 */
	public static function fromBinary($binary, $internal = false, &$offset = null){

		$packet = new EncapsulatedPacket();

		$flags = ord($binary{0});
		$packet->reliability = $reliability = ($flags & 0b11100000) >> 5;
		$packet->hasSplit = $hasSplit = ($flags & 0b00010000) > 0;
		if($internal){
			$length = Binary::readInt(substr($binary, 1, 4));
			$packet->identifierACK = Binary::readInt(substr($binary, 5, 4));
			$offset = 9;
		}else{
			$length = (int) ceil(Binary::readShort(substr($binary, 1, 2)) / 8);
			$offset = 3;
			$packet->identifierACK = null;
		}

		if($reliability > PacketReliability::UNRELIABLE){
			if($reliability >= PacketReliability::RELIABLE and $reliability !== PacketReliability::UNRELIABLE_WITH_ACK_RECEIPT){
				$packet->messageIndex = Binary::readLTriad(substr($binary, $offset, 3));
				$offset += 3;
			}

			if($reliability <= PacketReliability::RELIABLE_SEQUENCED and $reliability !== PacketReliability::RELIABLE){
				$packet->orderIndex = Binary::readLTriad(substr($binary, $offset, 3));
				$offset += 3;
				$packet->orderChannel = ord($binary{$offset++});
			}
		}

		if($hasSplit){
			$packet->splitCount = Binary::readInt(substr($binary, $offset, 4));
			$offset += 4;
			$packet->splitID = Binary::readShort(substr($binary, $offset, 2));
			$offset += 2;
			$packet->splitIndex = Binary::readInt(substr($binary, $offset, 4));
			$offset += 4;
		}

		$packet->buffer = substr($binary, $offset, $length);
		$offset += $length;

		return $packet;
	}

	/**
	 * Returns the resulting total encoded length of the encapsulated packet.
	 * @return int
	 */
	public function getTotalLength(){
		return $this->getHeaderLength() + strlen($this->buffer);
	}

	/**
	 * Returns the resulting encoded header length without the buffer.
	 * @return int
	 */
	public function getHeaderLength() : int{
		return
			1 + 2 + ($this->messageIndex !== null ? 3 : 0) + ($this->orderIndex !== null ? 3 + 1 : 0) + ($this->hasSplit ? 4 + 2 + 4 : 0);
	}

	/**
	 * @param bool $internal
	 *
	 * @return string
	 */
	public function toBinary($internal = false){
		return
			chr(($this->reliability << 5) | ($this->hasSplit ? 0b00010000 : 0)) .
			($internal ? Binary::writeInt(strlen($this->buffer)) . Binary::writeInt($this->identifierACK) : Binary::writeShort(strlen($this->buffer) << 3)) .
			($this->reliability > PacketReliability::UNRELIABLE ?
				(($this->reliability >= PacketReliability::RELIABLE and $this->reliability !== PacketReliability::UNRELIABLE_WITH_ACK_RECEIPT) ? Binary::writeLTriad($this->messageIndex) : "") .
				(($this->reliability <= PacketReliability::RELIABLE_SEQUENCED and $this->reliability !== PacketReliability::RELIABLE) ? Binary::writeLTriad($this->orderIndex) . chr($this->orderChannel) : "")
				: ""
			) .
			($this->hasSplit ? Binary::writeInt($this->splitCount) . Binary::writeShort($this->splitID) . Binary::writeInt($this->splitIndex) : "")
			. $this->buffer;
	}

	public function __toString(){
		return $this->toBinary();
	}
}
