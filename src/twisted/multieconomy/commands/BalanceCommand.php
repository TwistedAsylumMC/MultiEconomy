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

class BalanceCommand extends PluginCommand {

	/** @var MultiEconomy $plugin */
	private $plugin;

	public function __construct(MultiEconomy $plugin){
		parent::__construct("balance", $plugin);

		$this->setAliases(["bal"]);
		$this->setDescription("Check yours or another targets balance");

		$this->plugin = $plugin;
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args): void{
		if(empty($currencies = $this->plugin->getCurrencies())){
			$sender->sendMessage($this->plugin->translateMessage("no-currencies-configured"));

			return;
		}

		if(empty($args[0])){
			if(!$sender instanceof Player){
				$sender->sendMessage($this->plugin->translateMessage("use-command-in-game"));

				return;
			}

			$sender->sendMessage($this->plugin->translateMessage("list-currencies"));

			foreach($currencies as $currency){
				$sender->sendMessage($this->plugin->translateMessage("own-balance", [
					"currency" => $currency->getName(),
					"balance"  => $currency->formatBalance($currency->getBalance($sender->getName())),
				]));
			}

			return;
		}

		if($sender instanceof Player && ($currency = $currencies[strtolower($args[0])] ?? null) !== null){
			$sender->sendMessage($this->plugin->translateMessage("own-balance", [
				"currency" => $currency->getName(),
				"balance"  => $currency->formatBalance($currency->getBalance($sender->getName())),
			]));

			return;
		}

		if(!($target = ($sender->getServer()->getPlayer($args[0]) ?? $sender->getServer()->getOfflinePlayer($args[0])))->hasPlayedBefore()){
			$sender->sendMessage($this->plugin->translateMessage("target-not-played", [
				"target" => $target->getName(),
			]));

			return;
		}

		if(count($args) === 1){
			$sender->sendMessage($this->plugin->translateMessage("list-currencies-other", [
				"target" => $target->getName(),
			]));

			foreach($currencies as $currency){
				$sender->sendMessage($this->plugin->translateMessage("other-balance", [
					"target"   => $target->getName(),
					"currency" => $currency->getName(),
					"balance"  => $currency->formatBalance($currency->getBalance($target->getName())),
				]));
			}

			return;
		}

		if(($currency = $currencies[strtolower($args[0])] ?? null) !== null){
			$sender->sendMessage($this->plugin->translateMessage("own-balance", [
				"target"   => $target->getName(),
				"currency" => $currency->getName(),
				"balance"  => $currency->formatBalance($currency->getBalance($target->getName())),
			]));

			return;
		}

		$sender->sendMessage($this->plugin->translateMessage("currency-not-found", [
			"currency"   => strtolower($args[0]),
			"currencies" => implode(",", $this->plugin->getCurrencyNames()),
		]));
	}
}