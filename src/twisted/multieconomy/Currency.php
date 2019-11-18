<?php
declare(strict_types=1);

namespace twisted\multieconomy;

use PDO;
use pocketmine\Server;
use pocketmine\utils\Config;
use twisted\multieconomy\tasks\AsyncQueryCallbackTask;
use function abs;
use function array_chunk;
use function asort;
use function count;
use function max;
use function min;
use function number_format;
use function strtolower;

class Currency{

    /** @var string $name */
    private $name;

    /** @var string $symbol */
    private $symbol;
    /** @var bool $symbolAfter */
    private $symbolAfter;

    /** @var float $startingAmount */
    private $startingAmount;

    /** @var float $minAmount */
    private $minAmount;
    /** @var float $maxAmount */
    private $maxAmount;

    /** @var Config|PDO */
    private $database;
    /** @var string */
    private $provider;
    /** @var float[] */
    private $cache = [];

    public function __construct(string $name, string $symbol, bool $symbolAfter, float $startingAmount, float $minAmount, float $maxAmount){
        $this->name = $name;
        $this->symbol = $symbol;
        $this->symbolAfter = $symbolAfter;
        $this->startingAmount = max(0.0, $startingAmount);
        $this->minAmount = min($minAmount, $maxAmount);
        $this->maxAmount = max($minAmount, $maxAmount);

        $this->loadDatabase();
    }

    /**
     * Internal function to load & prepare the database
     * Loads then caches any data from existing databases
     */
    private function loadDatabase() : void{
        $path = MultiEconomy::getInstance()->getDataFolder() . "currencies/";

        $config = MultiEconomy::getInstance()->getConfig();
        $info = $config->get("database");

        switch(($this->provider = $provider = strtolower($info["provider"])) ?? "sqlite"){
            case "yml":
            case "json":
                $this->database = $database = new Config($path . $this->getLowerName() . "." . $provider);

                $this->cache = $database->getAll();
                break;
            case "sqlite":
                $this->database = $database = new PDO("sqlite:" . $path . $this->getLowerName() . ".sqlite");
                $database->exec("CREATE TABLE IF NOT EXISTS players(username VARCHAR(16) NOT NULL PRIMARY KEY, balance FLOAT NOT NULL DEFAULT 0)");

                $stmt = $database->query("SELECT username, balance FROM players");
                $stmt->execute();

                $data = $stmt->fetchAll();
                foreach($data as $datum){
                    $this->cache[$datum["username"]] = (float) $datum["balance"];
                }
                break;
            case "mysql":
                $creds = $info["mysql"];

                $host = $creds["host"] ?? "localhost";
                $schema = $creds["schema"] ?? "multieconomy";
                $username = $creds["username"] ?? "root";
                $password = $creds["password"] ?? "";

                $this->database = $database = new PDO("mysql:host=" . $host . ";dbname=" . $schema, $username, $password);
                $database->exec("CREATE TABLE IF NOT EXISTS " . $this->getLowerName() . "(username VARCHAR(16) NOT NULL PRIMARY KEY, balance FLOAT NOT NULL DEFAULT 0)");

                $stmt = $database->query("SELECT username, balance FROM players");
                $stmt->execute();

                $data = $stmt->fetchAll();
                foreach($data as $datum){
                    $this->cache[$datum["username"]] = (float) $datum["balance"];
                }
                break;
        }
    }

    /**
     * Gets the name of the currency
     * in all lowercase letters
     *
     * @return string
     */
    public function getLowerName() : string{
        return strtolower($this->name);
    }

    /**
     * Get the name of the currency in
     * it's exact casing in the config
     *
     * @return string
     */
    public function getName() : string{
        return $this->name;
    }

    /**
     * Gets the symbol/sign for the currency
     *
     * @return string
     */
    public function getSymbol() : string{
        return $this->symbol;
    }

    /**
     * Used for formatting balances in the currency
     * Determines wether the symbol/sign should go
     * before or after the balance
     *
     * @return bool
     */
    public function isSymbolAfter() : bool{
        return $this->symbolAfter;
    }

    /**
     * Uses isSymbolAfter() to format the balance correctly
     *
     * @param float $balance
     *
     * @return string
     */
    public function formatBalance(float $balance) : string{
        return $this->symbolAfter ? number_format($balance) . $this->symbol : $this->symbol . number_format($balance);
    }

    /**
     * Gets the default balance for players
     * who do not have an existing balance
     *
     * @return float
     */
    public function getStartingAmount() : float{
        return $this->startingAmount;
    }

    /**
     * Gets the minimum amount a balance can have at once
     *
     * @return float
     */
    public function getMinAmount() : float{
        return $this->minAmount;
    }

    /**
     * Gets the maximum amount a balance can have at once
     *
     * @return float
     */
    public function getMaxAmount() : float{
        return $this->maxAmount;
    }

    /**
     * Saves all cached data to the currency's database
     *
     * This should not be called too often,
     * especially if using mysql or sqlite
     */
    public function save() : void{
        if(empty($this->cache)){
            return;
        }

        $database = $this->database;
        if($database instanceof Config){
            foreach($this->cache as $player => $info){
                $database->set(strtolower($player), $info["balance"] ?? 0);
            }
            $database->save();
        }elseif($database instanceof PDO){
            if($this->provider === "mysql"){
                foreach($this->cache as $username => $balance){
                    Server::getInstance()->getAsyncPool()->submitTask(new AsyncQueryCallbackTask($this->database, "INSERT OR REPLACE INTO " . $this->getLowerName() . "(username, balance) VALUES(:username, :balance)", [
                        ":username" => $username,
                        ":balance" => $balance
                    ]));
                }
            }elseif($this->provider === "sqlite"){
                foreach($this->cache as $username => $balance){
                    $stmt = $database->prepare("INSERT OR REPLACE INTO players(username, balance) VALUES(:username, :balance)");
                    $stmt->execute([
                        ":username" => $username,
                        ":balance" => $balance
                    ]);
                }
            }
        }
    }

    /**
     * Adds an amount to the player's balance
     *
     * @param string $username
     * @param float  $amount
     *
     * @return bool
     */
    public function addToBalance(string $username, float $amount) : bool{
        if($amount <= 0){
            return $amount !== 0 && $this->removeFromBalance($username, abs($amount));
        }

        if(isset($this->cache[strtolower($username)])){
            $this->cache[strtolower($username)] += $amount;
        }else{
            $this->cache[strtolower($username)] = $amount;
        }

        $this->validateBalance($username);

        return true;
    }

    /**
     * Removes an amount to the player's balance
     *
     * @param string $username
     * @param float  $amount
     *
     * @return bool
     */
    public function removeFromBalance(string $username, float $amount) : bool{
        if($amount <= 0){
            return $amount !== 0 && $this->addToBalance($username, abs($amount));
        }

        if(isset($this->cache[strtolower($username)])){
            $this->cache[strtolower($username)] -= $amount;
        }else{
            $this->cache[strtolower($username)] = $amount;
        }

        $this->validateBalance($username);

        return true;
    }

    /**
     * Checks if the player's balance is within the min & max values
     * It then automatically corrects the player's balance if needed
     *
     * @param string $username
     *
     * @return bool
     */
    public function validateBalance(string $username) : bool{
        if(!isset($this->cache[strtolower($username)])){
            if($this->startingAmount > 0){
                $this->cache[strtolower($username)] = $this->startingAmount;
            }

            return false;
        }

        if($this->cache[strtolower($username)] < $this->minAmount){
            $this->cache[strtolower($username)] = $this->minAmount;

            return false;
        }

        if($this->cache[strtolower($username)] > $this->maxAmount){
            $this->cache[strtolower($username)] = $this->maxAmount;

            return false;
        }

        return true;
    }

    /**
     * Get a specific player's balance
     * Returns the starting amount if they do not
     * have a balance, and adds them to the cache
     *
     * @param string $username
     *
     * @return float
     */
    public function getBalance(string $username) : float{
        if(!isset($this->cache[strtolower($username)])){
            $this->cache[strtolower($username)] = $this->startingAmount;

            return $this->startingAmount;
        }

        return $this->cache[strtolower($username)];
    }

    /**
     * Set a player's balance to the given value
     *
     * @param string $username
     * @param float  $amount
     *
     * @return bool
     */
    public function setBalance(string $username, float $amount) : bool{
        $this->cache[strtolower($username)] = $amount;

        $this->validateBalance($username);

        return true;
    }

    /**
     * Returns the top $size balances for the currency,
     * an ordered float array from richest to poorest
     * in the format of (string)username => (float)balance
     *
     * @param int $size
     * @param int $page
     *
     * @return float[]
     */
    public function getTopBalances(int $size, int $page) : array{
        $balances = $this->getAllBalances();
        asort($balances);
        $balances = array_chunk($balances, $size, true);

        $page = min(count($balances), max(1, $page));

        return $balances[$page - 1] ?? [];
    }

    /**
     * Returns all the balances in float array with
     * the format (string)username => (float)balance
     *
     * @return float[]
     */
    public function getAllBalances() : array{
        return $this->cache;
    }
}
