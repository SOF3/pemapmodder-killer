<?php

/*
 * pemapmodder-killer
 *
 * Copyright (C) 2018 SOFe
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace ShouldThisBe\PEMapModder\OrIsIt\SOFe\Killer;

use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;

class Settings{
	public const CHUNK_LENGTH_AMPLITUDE = 4; // a chunk is (1 << 4) * (1 << 4) large

	public const MIN_X = 0;
	public const MIN_Z = 0;

	public const GROUND_Y = 6;

	public const TOTAL_CHUNK_COUNT_ROOT = 16; // number of chunks on one side
	public const TOTAL_CHUNK_COUNT = self::TOTAL_CHUNK_COUNT_ROOT ** 2;

	public const BLOCK_CLICK_LIMIT = 3;

	public static function block2chunk(Vector3 $block) : Vector2{
		return new Vector2(floor($block->x - self::MIN_X) >> self::CHUNK_LENGTH_AMPLITUDE, floor($block->z - self::MIN_Z) >> self::CHUNK_LENGTH_AMPLITUDE);
	}

	public static function chunk2index(Vector2 $chunk) : int{
		return $chunk->x * self::TOTAL_CHUNK_COUNT_ROOT + $chunk->y;
	}

	public static function initChunks() : \SplFixedArray{
		$ret = new \SplFixedArray(self::TOTAL_CHUNK_COUNT);
		for($x = 0; $x < self::TOTAL_CHUNK_COUNT_ROOT >> 1; ++$x){
			for($z = 0; $z < self::TOTAL_CHUNK_COUNT_ROOT >> 1; ++$z){
				$ret[$x * self::TOTAL_CHUNK_COUNT_ROOT + $z] = 0;
			}
			for($z = self::TOTAL_CHUNK_COUNT_ROOT >> 1; $z < self::TOTAL_CHUNK_COUNT_ROOT; ++$z){
				$ret[$x * self::TOTAL_CHUNK_COUNT_ROOT + $z] = 1;
			}
		}
		for($x = self::TOTAL_CHUNK_COUNT_ROOT >> 1; $x < self::TOTAL_CHUNK_COUNT_ROOT; ++$x){
			for($z = 0; $z < self::TOTAL_CHUNK_COUNT_ROOT >> 1; ++$z){
				$ret[$x * self::TOTAL_CHUNK_COUNT_ROOT + $z] = 2;
			}
			for($z = self::TOTAL_CHUNK_COUNT_ROOT >> 1; $z < self::TOTAL_CHUNK_COUNT_ROOT; ++$z){
				$ret[$x * self::TOTAL_CHUNK_COUNT_ROOT + $z] = 3;
			}
		}
		return $ret;
	}

	public static function getLevel(Server $server) : Level{
		return $server->getDefaultLevel();
	}

	public static function spawnIn(Server $server, int $x, int $z) : Position{
		return new Position(
			self::MIN_X + (($x << self::CHUNK_LENGTH_AMPLITUDE) | (1 << (self::CHUNK_LENGTH_AMPLITUDE - 1))),
			self::GROUND_Y,
			self::MIN_Z + (($z << self::CHUNK_LENGTH_AMPLITUDE) | (1 << (self::CHUNK_LENGTH_AMPLITUDE - 1))),
			self::getLevel($server));
	}

	public static function isCriticalBlock(Block $block) : bool{
		return $block->getId() === Block::WOOL;
	}

	public static function equip(Player $player) : void{
		$player->getInventory()->addItem(Item::get(Item::IRON_SWORD), Item::get(Item::BOW), Item::get(Item::ARROW, 64));
		$player->getInventory()->setArmorItem(0, Item::get(Item::CHAIN_HELMET));
		$player->getInventory()->setArmorItem(1, Item::get(Item::CHAIN_CHESTPLATE));
		$player->getInventory()->setArmorItem(2, Item::get(Item::CHAIN_LEGGINGS));
		$player->getInventory()->setArmorItem(3, Item::get(Item::CHAIN_BOOTS));
	}
}
