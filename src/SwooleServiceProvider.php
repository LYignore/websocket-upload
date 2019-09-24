<?php
namespace Lyignore\WebsocketUpload;


use Lyignore\WebsocketUpload\Command\Swoole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Lyignore\WebsocketUpload\Support\Config;

class SwooleServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function register()
    {
    }

    public function boot()
    {
        // 注册配置文件
        $this->publishes([
            __DIR__ . '/../config/swoole.php' => config_path('swoole.php')
        ]);
        // 注册命令
        if($this->app->runningInConsole()){
            $config = config('swoole');
            Config::getInstance($config);
            $this->commands([
                Swoole::class,
            ]);
        }
    }

}