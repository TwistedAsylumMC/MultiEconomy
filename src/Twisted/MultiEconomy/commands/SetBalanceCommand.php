<?php
declare(strict_types=1);

namespace Twisted\MultiEconomy\commands;

use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use Twisted\MultiEconomy\MultiEconomy;

class SetBalanceCommand extends PluginCommand{

	/** @var MultiEconomy $plugin */
	private $plugin;

	public function __construct(MultiEconomy $plugin){
		$this->plugin = $plugin;
		parent::__construct("setbalance", $plugin);
		$this->setAliases(["setbal"]);
		$this->setDescription("Set a player's balance for a currency");
		$this->setPermission("multieconomy.setbalance");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(!$this->testPermission($sender)) return;
		$api = $this->plugin->getAPI();
		if(count($currencies = $api->getCurrencies()) === 0){
			$sender->sendMessage($api->getMessage("no-currencies-configured"));
			return;
		}
		if(empty($args[2])){
			$sender->sendMessage($api->getMessage("command-usage", [
				"{usage}" => "/setbalance <player> <currency> <balance>"
			]));
			return;
		}
		if(($target = $sender->getServer()->getPlayer($args[0])) == null){
			$sender->sendMessage($api->getMessage("player-not-played", [
				"{player}" => $target->getName()
			]));
			return;
		}
		if(empty($currencies[strtolower($args[1])])){
			$sender->sendMessage($api->getMessage("currency-not-found", [
				"{currency}" => $args[1],
				"{currencies}" => implode(",", $api->getCurrencyNames())
			]));
			return;
		}
		$currency = $currencies[strtolower($args[1])];
		if((int)$args[2] < 0){
			$sender->sendMessage($api->getMessage("value-not-valid"));
			return;
		}
		$api->setBalance($target->getName(), (string)$currency, (int)$args[2]);
		$sender->sendMessage($api->getMessage("target-balance-set", [
			"{target}" => $target->getName(),
			"{currency}" => $currency->getName(),
			"{balance}" => $currency->getDisplayFormat((string)$api->getBalance($target->getName(), $currency->getName()))
		]));
		$sender->sendMessage($api->getMessage("own-balance-set", [
			"{currency}" => $currency->getName(),
			"{balance}" => $currency->getDisplayFormat((string)$api->getBalance($target->getName(), $currency->getName()))
		]));
	}
}
