<?php

declare(strict_types = 1);

namespace twisted\multieconomy\database;

use InvalidArgumentException;
use pocketmine\utils\Config;
use twisted\multieconomy\MultiEconomy;

class FileDatabase implements Database {

	/** @var Config[] */
	private $database;
	/** @var string */
	private $provider;

	public function __construct(string $provider){
		$this->provider = $provider;
	}

	public function createCurrency(string $currency): void{
		$path = MultiEconomy::getInstance()->getDataFolder() . "currencies/";

		$config = new Config($path . $currency . "." . $this->provider);
		$this->database[$currency] = $config;
	}

	public function setBalance(string $playerName, float $totalCurrency, string $currency): void{
		if(!isset($this->database[$currency])) throw new InvalidArgumentException("Currency provided is not registered.");

		$db = $this->database[$currency];
		$db->set($playerName, $totalCurrency);
		$db->save(); // Not async
	}

	public function getAllBalance(callable $result, string $currency): void{
		if(!isset($this->database[$currency])) throw new InvalidArgumentException("Currency provided is not registered.");

		$db = $this->database[$currency];
		foreach($db->getAll() as $d => $o){
			$result($d, $o);
		}
	}

	public function shutdown(): void{}
}