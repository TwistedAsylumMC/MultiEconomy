<?php

declare(strict_types = 1);

namespace twisted\multieconomy\database;

use InvalidArgumentException;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use twisted\multieconomy\MultiEconomy;

class SqliteDatabase implements Database {

	/** @var DataConnector[] */
	private $currencies = [];

	/**
	 * @param string $currency
	 */
	public function createCurrency(string $currency): void{
		$database = libasynql::create(MultiEconomy::getInstance(), [
			"type"   => "sqlite",
			"sqlite" => ["file" => "$currency.sqlite"],
		], [
			"sqlite" => "scripts/sqlite.sql",
			"mysql"  => "scripts/mysql.sql",
		]);

		$database->executeGenericRaw("CREATE TABLE IF NOT EXISTS players(username VARCHAR(16) NOT NULL PRIMARY KEY, balance FLOAT NOT NULL DEFAULT 0)");
		$this->currencies[$currency] = $database;
	}

	/**
	 * @param string $playerName
	 * @param float $totalCurrency
	 * @param string $currency
	 */
	public function setBalance(string $playerName, float $totalCurrency, string $currency): void{
		if(!isset($this->currencies[$currency])) throw new InvalidArgumentException("Currency provided is not registered.");

		$this->currencies[$currency]->executeInsertRaw(
			"INSERT OR REPLACE INTO players(username, balance) VALUES (:userName, :balance)",
			[
				"userName" => $playerName,
				"balance"  => $totalCurrency,
			]);
	}

	public function getAllBalance(callable $result, string $currency): void{
		if(!isset($this->currencies[$currency])) throw new InvalidArgumentException("Currency provided is not registered.");

		$this->currencies[$currency]->executeSelectRaw("SELECT * FROM players", [], function(array $rows) use ($result){
			foreach($rows as $dat){
				$result($dat["username"], (float)$dat["balance"]);
			}
		});
		$this->currencies[$currency]->waitAll();
	}

	public function shutdown(): void{
		// Wait for all threads to shutdown.
		foreach($this->currencies as $database){
			$database->waitAll();
			$database->close();
		}
	}
}