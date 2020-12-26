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

class RemoveFromBalanceCommand extends PluginCommand {

	/** @var MultiEconomy $plugin */
	private $plugin;

	public function __construct(MultiEconomy $plugin){
		parent::__construct("removefrombalance", $plugin);

		$this->setAliases(["removefrombal"]);
		$this->setDescription("Remove from a players balance");
		$this->setPermission("multieconomy.removefrombalance");

		$this->plugin = $plugin;
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args): void{
		if(!$this->testPermission($sender)){
			return;
		}

		if(empty($currencies = $this->plugin->getCurrencies())){
			$sender->sendMessage($this->plugin->translateMessage("no-currencies-configured"));

			return;
		}

		if(count($args) < 3){
			$sender->sendMessage($this->plugin->translateMessage("command-usage", [
				"usage" => "/removefrombalance <target> <currency> <amount>",
			]));

			return;
		}

		if(!($target = ($sender->getServer()->getPlayer($args[0]) ?? $sender->getServer()->getOfflinePlayer($args[0])))->hasPlayedBefore()){
			$sender->sendMessage($this->plugin->translateMessage("target-not-found", [
				"target" => $args[0],
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
		if($amount <= 0 || $amount > $currency->getMaxAmount()){
			$sender->sendMessage($this->plugin->translateMessage("value-not-valid"));

			return;
		}

		$currency->removeFromBalance($target->getName(), $amount);

		if($sender->getName() !== $target->getName()){
			$sender->sendMessage($this->plugin->translateMessage("target-balance-removed", [
				"target"   => $target->getName(),
				"currency" => $currency->getName(),
				"amount"   => $currency->formatBalance($amount),
			]));
		}

		if($target instanceof Player){
			$target->sendMessage($this->plugin->translateMessage("own-balance-removed", [
				"target"   => $sender->getName(),
				"currency" => $currency->getName(),
				"amount"   => $currency->formatBalance($amount),
			]));
		}
	}
}