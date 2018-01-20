<?php

/*
 * pemapmodder-killer
 *
 * This is free and unencumbered software released into the public domain.
 * 
 * Anyone is free to copy, modify, publish, use, compile, sell, or
 * distribute this software, either in source code form or as a compiled
 * binary, for any purpose, commercial or non-commercial, and by any
 * means.
 * 
 * In jurisdictions that recognize copyright laws, the author or authors
 * of this software dedicate any and all copyright interest in the
 * software to the public domain. We make this dedication for the benefit
 * of the public at large and to the detriment of our heirs and
 * successors. We intend this dedication to be an overt act of
 * relinquishment in perpetuity of all present and future rights to this
 * software under copyright law.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS BE LIABLE FOR ANY CLAIM, DAMAGES OR
 * OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
 * ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 * 
 * For more information, please refer to <http://unlicense.org>
 */

declare(strict_types=1);

namespace ShouldThisBe\PEMapModder\OrIsIt\SOFe\Killer;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\level\Position;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener{
	/** @var \SplFixedArray|Team[] */
	private $teams;

	/** @var \SplFixedArray|int[] */
	private $map;

	/** @var \SplFixedArray|Position[] */
	private $spawnCache;

	/** @var \SplFixedArray|int[] */
	private $clickTimes;

	public function onEnable() : void{
		$this->teams = new \SplFixedArray(4);
		for($i = 0; $i < 4; ++$i){
			$this->teams[$i] = new Team($i);
		}

		$this->map = Settings::initChunks();

		$this->spawnCache = new \SplFixedArray(4);
		for($i = 0; $i < 4; ++$i){
			$this->spawnCache[$i] = $this->findSpawn($i);
		}

		$this->clickTimes = \SplFixedArray::fromArray(array_fill(0, $this->map->getSize(), 0));

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$this->onJoin(new PlayerJoinEvent($player, ""));
		}
	}

	public function onDisable() : void{
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$this->onQuit(new PlayerQuitEvent($player, "", "game restart"));
		}
	}

	public function e_onJoin(PlayerJoinEvent $event){
		$player = $event->getPlayer();
		$team = $this->findTeam(strtolower($player->getName()));
		$player->sendMessage("You are in Team {$team->getName()}");
		$event->setJoinMessage("{$player} joined Team {$team->getName()}!");
		$team->addPlayer($player);
		$player->teleport($this->spawnCache[$team->getId()]);
		Settings::equip($player);
	}

	public function e_onQuit(PlayerQuitEvent $event){
		foreach($this->teams as $team){
			if($team->hasPlayer($event->getPlayer())){
				$team->removePlayer($event->getPlayer());
				$event->setQuitMessage("{{$team->getName()}} {$event->getPlayer()} left!");
			}
		}
	}

	public function e_onRespawn(PlayerRespawnEvent $event){
		foreach($this->teams as $team){
			if($team->hasPlayer($player = $event->getPlayer())){
				$event->setRespawnPosition($this->spawnCache[$team->getId()]);
				Settings::equip($player);
				break;
			}
		}
	}

	/**
	 * @param PlayerChatEvent $event
	 * @ignoreCancelled true
	 */
	public function e_onChat(PlayerChatEvent $event){
		if($event->getMessage(){0} === "!"){
			$event->setMessage(substr($event->getMessage(), 1));
		}else{
			foreach($this->teams as $team){
				if($team->hasPlayer($event->getPlayer())){
					$event->setRecipients($team->getPlayers());
				}
			}
		}
	}

	/**
	 * @param BlockBreakEvent $event
	 * @priority        HIGH
	 * @ignoreCancelled true
	 */
	public function e_onBlockBreak(BlockBreakEvent $event){
		$event->setCancelled();
		if(Settings::isCriticalBlock($event->getBlock())){
			foreach($this->teams as $team){
				if(!$team->hasPlayer($event->getPlayer())){
					continue;
				}
				$chunk = Settings::block2chunk($event->getBlock());
				$index = Settings::chunk2index($chunk);
				$teamId = $team->getId();
				if($teamId === $this->map[$index] ||
					($index >= Settings::TOTAL_CHUNK_COUNT_ROOT && $teamId === $this->map[$index - Settings::TOTAL_CHUNK_COUNT_ROOT]) ||
					($index >= 1 && $teamId === $this->map[$index - 1]) ||
					($index + Settings::TOTAL_CHUNK_COUNT_ROOT < Settings::TOTAL_CHUNK_COUNT && $teamId === $this->map[$index + Settings::TOTAL_CHUNK_COUNT_ROOT]) ||
					($index + 1 < Settings::TOTAL_CHUNK_COUNT && $teamId === $this->map[$index + 1])
				){
					if($this->clickTimes[$index][0] !== $teamId){
						$this->clickTimes[$index] = [$teamId, 0];
					}
					if((++$this->clickTimes[$index][1]) >= Settings::BLOCK_CLICK_LIMIT){
						$this->clickTimes[$index] = [0, 0];
						$this->setChunk($chunk->x, $chunk->y, $teamId);
						/** @noinspection NullPointerExceptionInspection */
						$event->getBlock()->getLevel()->setBlock($event->getBlock(), $team->getBlock());
					}
				}
				break;
			}
		}
	}

	private function findTeam(string $playerIName) : Team{
		// TODO restore the player to his original team
		$minValue = PHP_INT_MAX;
		/** @var Team[] $minTeams */
		$minTeams = [];
		foreach($this->teams as $id => $team){
			if(\count($team->getPlayers()) < $minValue){
				$minValue = \count($team->getPlayers());
				$minTeams = [$id => $team];
			}elseif(\count($team->getPlayers()) === $minValue){
				$minTeams[$id] = $team;
			}
		}
		$selectedId = array_rand($minTeams);
		return $minTeams[$selectedId];
	}

	public function setChunk(int $x, int $z, int $wonTeamId) : void{
		$index = $x * Settings::TOTAL_CHUNK_COUNT_ROOT + $z;
		if($this->map[$index] === $wonTeamId){
			return;
		}
		$lostTeam = $this->teams[$this->map[$index]];
		$wonTeam = $this->teams[$wonTeamId];
		$this->map[$index] = $wonTeamId;

		$this->spawnCache = new \SplFixedArray(4);
		$this->getServer()->broadcastMessage("{$wonTeam->getName()} won a chunk from {$lostTeam->getName()}!");
		foreach($this->teams as $id => $team){
			if(!$team->hasLost()){
				$this->spawnCache[$id] = $pos = $this->findSpawn($id);
				if($pos === null){
					$team->lose();
					$this->getServer()->broadcastMessage("{$team->getName()} has been destroyed!");
				}
			}
		}
	}

	private function findSpawn(int $teamId) : ?Position{
		$heatmap = \SplFixedArray::fromArray(array_fill(0, Settings::TOTAL_CHUNK_COUNT, 0));
		for($x = 0; $x < Settings::TOTAL_CHUNK_COUNT_ROOT; ++$x){
			for($z = 0; $z < Settings::TOTAL_CHUNK_COUNT_ROOT; ++$z){
				$index = $x * Settings::TOTAL_CHUNK_COUNT_ROOT + $z;
				if($this->map[$index] === $teamId){
					self::capHeatmap($heatmap, $x, $z, Settings::TOTAL_CHUNK_COUNT_ROOT);
				}
			}
		}
		$min = Settings::TOTAL_CHUNK_COUNT_ROOT + 1;
		$indices = [];
		for($i = 0; $i < Settings::TOTAL_CHUNK_COUNT; ++$i){
			if($min > $heatmap[$i]){
				$min = $heatmap[$i];
				$indices = [$i];
			}elseif($min === $heatmap[$i]){
				$indices[] = $i;
			}
		}
		if(\count($indices) === 0){
			return null;
		}
		$index = rand(0, \count($indices) - 1);
		return Settings::spawnIn($this->getServer(), (int) ($index / Settings::TOTAL_CHUNK_COUNT_ROOT), $index % Settings::TOTAL_CHUNK_COUNT_ROOT);
	}

	private static function capHeatmap(\SplFixedArray $heatmap, int $x, int $z, int $amplitude, int $from = -1) : void{
		$index = $x * Settings::TOTAL_CHUNK_COUNT_ROOT + $z;
		if($heatmap[$index] < $amplitude){
			$heatmap[$index] = $amplitude;
			if($amplitude > 1){
				--$amplitude;
				if($from !== 1 && $x + 1 < Settings::TOTAL_CHUNK_COUNT_ROOT){
					self::capHeatmap($heatmap, $x + 1, $z, $amplitude, 0);
				}
				if($from !== 0 && $x > 0){
					self::capHeatmap($heatmap, $x - 1, $z, $amplitude, 1);
				}
				if($from !== 3 && $z + 1 < Settings::TOTAL_CHUNK_COUNT_ROOT){
					self::capHeatmap($heatmap, $x, $z + 1, $amplitude, 2);
				}
				if($from !== 2 && $z > 0){
					self::capHeatmap($heatmap, $x, $z - 1, $amplitude, 3);
				}
			}
		}
	}
}
