<?php

declare(strict_types=1);

namespace twisted\multieconomy\database;

use pocketmine\utils\Config;

class NormalDatabase implements Database {

	/** @var Config */
	private $database;

	public function __construct(string $path){
		$this->database = new Config($path);
	}

	public function setBalance(string $playerName, float $totalCurrency): void{
		$this->database->set($playerName, $totalCurrency);
		$this->database->save(); // Not async
	}

	public function getAllBalance(callable $result): void{
		foreach($this->database->getAll() as $d => $o){
			$result($d, $o);
		}
	}

	public function shutdown(): void{
		$this->database->save();
	}
}