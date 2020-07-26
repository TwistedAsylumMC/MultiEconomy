<?php

declare(strict_types = 1);

namespace twisted\multieconomy\database;

use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use twisted\multieconomy\MultiEconomy;

class LibAsyncDatabase implements Database {

	const TABLE_CREATE_DATA = "economy.table";
	const TABLE_INSERT_DATA = "economy.insert";
	const TABLE_FETCH_DATA = "economy.select";

	/** @var DataConnector */
	private $database;

	public function __construct(?array $config, string $currName, bool $isMysql){
		if($isMysql){
			MultiEconomy::$replaceDialect = $currName;
			$this->database = libasynql::create(MultiEconomy::getInstance(), $config, [
				"sqlite" => "scripts/sqlite.sql",
				"mysql"  => "scripts/mysql.sql",
			]);
		}else{
			$this->database = libasynql::create(MultiEconomy::getInstance(), [
				"type"   => "sqlite",
				"sqlite" => ["file" => "$currName.sqlite"],
			], [
				"sqlite" => "scripts/sqlite.sql",
				"mysql"  => "scripts/mysql.sql",
			]);
		}

		$this->database->executeGeneric(self::TABLE_CREATE_DATA);
	}

	public function getAllBalance(callable $result): void{
		$this->database->executeSelect(self::TABLE_FETCH_DATA, [], function(array $rows) use ($result){
			foreach($rows as $datum){
				$result($datum["username"], (float)$datum["balance"]);
			}
		});

		$this->database->waitAll();
	}

	public function setBalance(string $playerName, float $totalCurrency): void{
		$this->database->executeInsert(self::TABLE_INSERT_DATA, [
			"userName" => $playerName,
			"balance"  => $totalCurrency,
		]);
	}

	public function shutdown(): void{
		$this->database->close();
	}
}