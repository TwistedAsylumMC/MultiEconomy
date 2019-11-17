<?php
declare(strict_types=1);

namespace twisted\multieconomy\tasks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use function serialize;
use function unserialize;

class AsyncQueryCallbackTask extends AsyncTask{

    /** @var \PDO */
    private $database;

    /** @var string */
    private $query;
    private $params;

    /** @var callable|null */
    private $onComplete;

    public function __construct(\PDO $database, string $query, array $params = [], ?callable $onComplete = null){
        $this->database = $database;

        $this->query = $query;
        $this->params = serialize($params);

        $this->onComplete = $onComplete;
    }

    public function onRun() : void{
        $stmt = $this->database->prepare($this->query);
        $stmt->execute(unserialize($this->params));

        $this->setResult($stmt);
    }

    public function onCompletion(Server $server) : void{
        $onComplete = $this->onComplete;
        if($onComplete !== null){
            $onComplete($this->getResult());
        }
    }
}