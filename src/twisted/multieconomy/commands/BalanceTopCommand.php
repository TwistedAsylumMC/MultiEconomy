<?php
declare(strict_types=1);

namespace twisted\multieconomy\commands;

use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat;
use twisted\multieconomy\MultiEconomy;
use function array_keys;
use function array_values;
use function count;
use function implode;
use function strtolower;
use function var_dump;

class BalanceTopCommand extends PluginCommand{

    /** @var MultiEconomy $plugin */
    private $plugin;

    public function __construct(MultiEconomy $plugin){
        parent::__construct("balancetop", $plugin);

        $this->setAliases(["baltop"]);
        $this->setDescription("Show the top balances for a currency");

        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args){
        if(empty($currencies = $this->plugin->getCurrencies())){
            $sender->sendMessage($this->plugin->translateMessage("no-currencies-configured"));

            return;
        }

        if(empty($args[0])){
            $sender->sendMessage($this->plugin->translateMessage("command-usage", [
                "usage" => "/balancetop <currency> [page]"
            ]));

            return;
        }

        if(($currency = $currencies[strtolower($args[0])] ?? null) === null){
            $sender->sendMessage($this->plugin->translateMessage("currency-not-found", [
                "currency" => $args[0],
                "currencies" => implode(", ", $this->plugin->getCurrencyNames())
            ]));

            return;
        }

        $page = (int) ($args[1] ?? 1);
        $top = $currency->getTopBalances(10, $page);

        if(empty($top)){
            $sender->sendMessage($this->plugin->translateMessage("no-top-balances", [
                "currency" => $currency->getName(),
                "page" => $page
            ]));

            return;
        }

        $message = $this->plugin->translateMessage("top-balances-title", [
            "currency" => $currency->getName(),
            "page" => $page
        ]);

        for($i = 0; $i < count($top); ++$i){
            $message .= TextFormat::EOL . $this->plugin->translateMessage("top-balances-format", [
                "place" => $i + 1,
                "player" => array_keys($top)[$i],
                "balance" => $currency->formatBalance(array_values($top)[$i])
            ]);
        }

        $sender->sendMessage($message);
    }
}