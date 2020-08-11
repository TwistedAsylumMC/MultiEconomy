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

	public function createCurrency(string $currency): void;

	public function setBalance(string $playerName, float $totalCurrency, string $currency): void;

	public function getAllBalance(callable $result, string $currency): void;

	public function shutdown(): void;
}