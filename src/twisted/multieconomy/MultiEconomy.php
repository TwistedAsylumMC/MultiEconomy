<?php
declare(strict_types = 1);

namespace twisted\multieconomy;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use twisted\multieconomy\commands\AddToBalanceCommand;
use twisted\multieconomy\commands\BalanceCommand;
use twisted\multieconomy\commands\BalanceTopCommand;
use twisted\multieconomy\commands\PayCommand;
use twisted\multieconomy\commands\RemoveFromBalanceCommand;
use twisted\multieconomy\commands\SetBalanceCommand;
use twisted\multieconomy\database\Database;
use twisted\multieconomy\database\FileDatabase;
use twisted\multieconomy\database\MysqlDatabase;
use twisted\multieconomy\database\SqliteDatabase;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function in_array;
use function is_array;
use function str_replace;
use function strtolower;

class MultiEconomy extends PluginBase implements Listener {

	/** @var MultiEconomy */
	private static $instance;
	/** @var Currency[] */
	private $currencies = [];
	/** @var Database */
	public $database;

	/**
	 * @return MultiEconomy
	 */
	public static function getInstance(): MultiEconomy{
		return self::$instance;
	}

	public function onLoad(){
		self::$instance = $this;
	}

	private function registerDatabase(): void{
		$config = MultiEconomy::getInstance()->getConfig();
		$info = $config->get("database");

		switch(($provider = strtolower($info["provider"])) ?? "sqlite"){
			case "yml":
			case "json":
				$this->database = new FileDatabase(strtolower($provider));
				break;
			case "sqlite":
				$this->database = new SqliteDatabase();
				break;
			case "mysql":
				$credentials = $info["mysql"];

				$this->database = new MysqlDatabase([
					"type"  => "mysql",
					"mysql" => $credentials,
				]);
				break;
		}
	}

	public function onEnable(): void{
		@mkdir($this->getDataFolder() . "currencies");
		@mkdir($this->getDataFolder() . "lang");
		@mkdir($this->getDataFolder() . "temp");

		$config = $this->getConfig();

		if($this->getLanguageCode() !== (string)$config->get("lang")){
			$config->set("lang", "eng");
			$config->save();

			$this->getLogger()->notice("Provided language is not supported. Setting the language back to the default value (eng)");
		}
		$this->saveResource("lang/" . $this->getLanguageCode() . ".yml");

		$this->registerDatabase();
		$currencies = $config->get("currencies", []);
		if(is_array($currencies)){
			foreach($currencies as $currency => $data){
				$name = (string)($data["name"] ?? $currency);
				$symbol = (string)($data["symbol"] ?? "$");
				$symbolAfter = (bool)($data["symbol-after"] ?? false);
				$starting = (int)($data["starting-amount"] ?? 0);
				$min = (int)($data["min-amount"] ?? 0);
				$max = (int)($data["max-amount"] ?? 0);

				$this->registerCurrency(new Currency($name, $symbol, $symbolAfter, $starting, $min, $max));
			}
		}

		$this->getServer()->getCommandMap()->registerAll("MultiCommand", [
			new AddToBalanceCommand($this),
			new BalanceCommand($this),
			new BalanceTopCommand($this),
			new PayCommand($this),
			new RemoveFromBalanceCommand($this),
			new SetBalanceCommand($this),
		]);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	/**
	 * Gets the 3 letter language code used for the plugin
	 *
	 * @return string
	 */
	public function getLanguageCode(): string{
		$lang = (string)$this->getConfig()->get("lang");
		$supported = [
			"eng",
			"chn",
		];

		return in_array(strtolower($lang), $supported, true) ? strtolower($lang) : "eng";
	}

	/**
	 * Registers a new currency, indexed by its lowercase name
	 *
	 * @param Currency $currency
	 *
	 * @return bool
	 */
	public function registerCurrency(Currency $currency): bool{
		$this->currencies[$currency->getLowerName()] = $currency;

		return true;
	}

	/**
	 * Get a currency by its name, returns null if not found
	 *
	 * @param string $name
	 *
	 * @return Currency|null
	 */
	public function getCurrency(string $name): ?Currency{
		return $this->currencies[strtolower($name)] ?? null;
	}

	public function onDisable(): void{
		foreach($this->getCurrencies() as $currency){
			$currency->save();
		}
	}

	/**
	 * Returns all the registered currencies
	 *
	 * @return Currency[]
	 */
	public function getCurrencies(): array{
		return $this->currencies;
	}

	/**
	 * Gets the translation from the language
	 * config and parses all parameters
	 *
	 * @param string $identifier
	 * @param array $params
	 *
	 * @return string
	 */
	public function translateMessage(string $identifier, array $params = []): string{
		$lang = $this->getLanguageConfig()->getAll();

		$message = (string)($lang[$identifier] ?? "&8[&9MultiEconomy&8]&7 Language identifier not found");
		$prefix = (string)($lang["prefix"] ?? "&8[&9MultiEconomy&8]&7");

		$search = array_merge(["{prefix}", "&"], array_map(static function(string $key): string{
			return "{" . $key . "}";
		}, array_keys($params)));
		$replace = array_merge([$prefix, TextFormat::ESCAPE], array_values($params));

		$message = str_replace($search, $replace, $message);

		return $message;
	}

	/**
	 * Returns the configuration file for the plugin's language
	 *
	 * @return Config
	 */
	public function getLanguageConfig(): Config{
		return new Config($this->getDataFolder() . "lang/" . $this->getLanguageCode() . ".yml", Config::YAML);
	}

	/**
	 * Returns a string array of all the currency names
	 *
	 * @return string[]
	 */
	public function getCurrencyNames(): array{
		return array_map(static function(Currency $currency): string{
			return $currency->getName();
		}, $this->currencies);
	}

	/**
	 * @param PlayerJoinEvent $event
	 */
	public function onPlayerJoin(PlayerJoinEvent $event): void{
		foreach($this->getCurrencies() as $currency){
			$currency->validateBalance($event->getPlayer()->getName());
		}
	}
}