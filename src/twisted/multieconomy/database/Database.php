<?php

declare(strict_types = 1);

namespace twisted\multieconomy\database;

/**
 * A database interpreter class.
 *
 * @author larryTheCoder
 * @package twisted\multieconomy\database
 */
interface Database {

	public function setBalance(string $playerName, float $totalCurrency): void;

	public function getAllBalance(callable $result): void;

	public function shutdown(): void;
}