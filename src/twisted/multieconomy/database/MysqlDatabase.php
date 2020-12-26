<?php

declare(strict_types = 1);

namespace twisted\multieconomy\database;

use InvalidArgumentException;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use twisted\multieconomy\MultiEconomy;

class MysqlDatabase implements Database {

	/** @var DataConnector */
	private $database;
	/** @var string[] */
	private $currencies = [];

	public function __construct(array $data){
		$this->database = libasynql::create(MultiEconomy::getInstance(), $data, [
			"sqlite" => "scripts/sqlite.sql",
			"mysql"  => "scripts/mysql.sql",
		]);
	}

	/**
	 * @param string $currency
	 */
	public function createCurrency(string $currency): void{
		$this->database->executeGenericRaw("CREATE TABLE IF NOT EXISTS {$currency}(username VARCHAR(16) NOT NULL PRIMARY KEY, balance FLOAT NOT NULL DEFAULT 0)");
		$this->currencies[$currency] = $currency;
	}

	/**
	 * @param string $playerName
	 * @param float $totalCurrency
	 * @param string $currency
	 */
	public function setBalance(string $playerName, float $totalCurrency, string $currency): void{
		if(!isset($this->currencies[$currency])) throw new InvalidArgumentException("Currency provided is not registered.");

		$this->database->executeInsertRaw(
			"INSERT INTO {$currency}(username, balance) VALUES (:userName, :balance) ON DUPLICATE KEY UPDATE balance = :balance",
			[
				"userName" => $playerName,
				"balance"  => $totalCurrency,
			]);
	}

	public function getAllBalance(callable $result, string $currency): void{
		if(!isset($this->currencies[$currency])) throw new InvalidArgumentException("Currency provided is not registered.");

		$this->database->executeSelectRaw("SELECT * FROM $currency", [], function(array $rows) use ($result){
			foreach($rows as $dat){
				$result($dat["username"], (float)$dat["balance"]);
			}
		});
		$this->database->waitAll();
	}

	public function shutdown(): void{
		$this->database->waitAll();
		$this->database->close();
	}
}