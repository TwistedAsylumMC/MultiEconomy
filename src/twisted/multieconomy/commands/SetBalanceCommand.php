<?php
declare(strict_types = 1);

namespace twisted\multieconomy\commands;

use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use twisted\multieconomy\MultiEconomy;
use function count;
use function implode;
use function strtolower;

class SetBalanceCommand extends PluginCommand {

	/** @var MultiEconomy $plugin */
	private $plugin;

	public function __construct(MultiEconomy $plugin){
		parent::__construct("setbalance", $plugin);

		$this->setAliases(["setbal"]);
		$this->setDescription("Set a player's balance for a currency");
		$this->setPermission("multieconomy.setbalance");

		$this->plugin = $plugin;
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(!$this->testPermission($sender)){
			return;
		}

		if(empty($currencies = $this->plugin->getCurrencies())){
			$sender->sendMessage($this->plugin->translateMessage("no-currencies-configured"));

			return;
		}

		if(count($args) < 3){
			$sender->sendMessage($this->plugin->translateMessage("command-usage", [
				"usage" => "/setbalance <player> <currency> <balance>",
			]));

			return;
		}

		if(!($target = ($sender->getServer()->getPlayer($args[0]) ?? $sender->getServer()->getOfflinePlayer($args[0])))->hasPlayedBefore()){
			$sender->sendMessage($this->plugin->translateMessage("player-not-played", [
				"player" => $target->getName(),
			]));

			return;
		}

		if(($currency = $currencies[strtolower($args[1])] ?? null) === null){
			$sender->sendMessage($this->plugin->translateMessage("currency-not-found", [
				"currency"   => $args[1],
				"currencies" => implode(", ", $this->plugin->getCurrencyNames()),
			]));

			return;
		}

		$amount = (float)$args[2];
		if($amount < $currency->getMinAmount() || $amount > $currency->getMaxAmount()){
			$sender->sendMessage($this->plugin->translateMessage("value-not-valid"));

			return;
		}

		$currency->setBalance($target->getName(), $amount);

		if($sender->getName() !== $target->getName()){
			$sender->sendMessage($this->plugin->translateMessage("target-balance-set", [
				"target"   => $target->getName(),
				"currency" => $currency->getName(),
				"balance"  => $currency->formatBalance($amount),
			]));
		}

		if($target instanceof Player){
			$target->sendMessage($this->plugin->translateMessage("own-balance-set", [
				"currency" => $currency->getName(),
				"balance"  => $currency->formatBalance($amount),
			]));
		}
	}
}
