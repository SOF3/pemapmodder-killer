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
use pocketmine\block\BlockIds;
use pocketmine\Player;

class Team{
	/** @var int */
	private $id;
	/** @var string */
	private $name;

	/** @var Block */
	private $block;

	/** @var Player[] */
	private $players = [];

	/** @var bool */
	private $hasLost = false;

	public function __construct(int $id){
		static $names = [
			"Pocket", "Mine", "Craft", "Plug"
		];
		$this->name = $names[$id];
		switch($id){
			case 0:
				$this->block = Block::get(BlockIds::WOOL, 2);
				break;
			case 1:
				$this->block = Block::get(BlockIds::WOOL, 3);
				break;
			case 2:
				$this->block = Block::get(BlockIds::WOOL, 4);
				break;
			case 3:
				$this->block = Block::get(BlockIds::WOOL, 5);
				break;
			default:
				throw new \InvalidArgumentException("Unknown name");
		}
		$this->id = $id;
	}

	public function getId() : int{
		return $this->id;
	}

	public function getName() : string{
		return $this->name;
	}

	public function getBlock() : Block{
		return $this->block;
	}

	public function getPlayers() : array{
		return $this->players;
	}

	public function addPlayer(Player $player) : void{
		$this->players[$player->getId()] = $player;
	}

	public function hasPlayer(Player $player) : bool{
		return isset($this->players[$player->getId()]);
	}

	public function removePlayer(Player $player) : void{
		unset($this->players[$player->getId()]);
	}

	public function hasLost() : bool{
		return $this->hasLost;
	}

	public function lose() : void{
		$this->hasLost = true;

		foreach($this->players as $player){
			$player->setGamemode(Player::SPECTATOR);
		}
	}
}
