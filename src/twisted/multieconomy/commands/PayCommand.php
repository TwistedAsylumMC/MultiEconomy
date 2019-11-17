<?php
declare(strict_types=1);

namespace twisted\multieconomy\commands;

use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use twisted\multieconomy\MultiEconomy;
use function count;
use function implode;
use function strtolower;

class PayCommand extends PluginCommand{

    /** @var MultiEconomy $plugin */
    private $plugin;

    public function __construct(MultiEconomy $plugin){
        $this->plugin = $plugin;
        parent::__construct("pay", $plugin);
        $this->setDescription("Pay another player money for a currency");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
        if(empty($currencies = $this->plugin->getCurrencies())){
            $sender->sendMessage($this->plugin->translateMessage("no-currencies-configured"));

            return;
        }

        if(count($args) < 3){
            $sender->sendMessage($this->plugin->translateMessage("command-usage", [
                "usage" => "/pay <target> <currency> <amount>"
            ]));

            return;
        }

        if(!($target = ($sender->getServer()->getPlayer($args[0]) ?? $sender->getServer()->getOfflinePlayer($args[0])))->hasPlayedBefore()){
            $sender->sendMessage($this->plugin->translateMessage("target-not-played", [
                "target" => $args[0]
            ]));

            return;
        }

        if($sender->getName() === $target->getName()){
            $sender->sendMessage($this->plugin->translateMessage("cannot-pay-self"));

            return;
        }

        if(($currency = $currencies[strtolower($args[1])] ?? null) === null){
            $sender->sendMessage($this->plugin->translateMessage("currency-not-found", [
                "currency" => $args[1],
                "currencies" => implode(", ", $this->plugin->getCurrencyNames())
            ]));

            return;
        }

        $amount = (float) $args[2];
        if($amount <= 0 || $amount > $currency->getMaxAmount()){
            $sender->sendMessage($this->plugin->translateMessage("value-not-valid"));

            return;
        }

        if($currency->getBalance($sender->getName()) < $amount){
            $sender->sendMessage($this->plugin->translateMessage("not-enough-money"));

            return;
        }

        $currency->removeFromBalance($sender->getName(), $amount);
        $currency->addToBalance($target->getName(), $amount);

        $sender->sendMessage($this->plugin->translateMessage("payment-sent", [
            "target" => $target->getName(),
            "currency" => $currency->getName(),
            "amount" => $currency->formatBalance($amount)
        ]));

        if($target instanceof Player){
            $target->sendMessage($this->plugin->translateMessage("payment-received", [
                "target" => $sender->getName(),
                "currency" => $currency,
                "amount" => $currency->formatBalance($amount)
            ]));
        }
    }
}