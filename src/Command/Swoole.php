<?php

namespace Lyignore\WebsocketUpload\Command;

use Illuminate\Console\Command;
use Lyignore\WebsocketUpload\WebsocketUpload;

class Swoole extends Command
{
    protected $server;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "swoole:{action?}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Swoole websocket uploadimg';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $action = $this->argument('action')?? 'start';
        switch($action){
            case 'close':
                $result = $this->close();
                break;
            default:
                $result = $this->start();
        }
        return $result;
    }

    protected function start()
    {
        $this->server = new WebsocketUpload();
        return $this->server->start();
    }

    protected function close()
    {
        if(! $this->server instanceof WebsocketUpload){
            $this->server = new WebsocketUpload();
        }
        return $this->server->allClose();
    }
}
