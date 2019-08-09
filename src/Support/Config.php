<?php
namespace Lyignore\WebsocketUpload\Support;

use ArrayAccess;
use Lyignore\WebsocketUpload\Traits\System;

class Config implements ArrayAccess
{
    use System;
    protected $config;
    protected $path = '';
    protected $loadFile;

    private static $instance;

    public function __construct(array $config = [])
    {
        $this->path = __DIR__.'/../Config';
        $this->autoLoad();
        if(!empty($config)){
            foreach ($config as $key => $val){
                $this->config[$key] = $val;
            }
        }
    }

    protected function autoLoad()
    {
        // 自动加载Config文件夹下的配置文件 ，传入的$config非空情况下
        $this->loadFile = $this->myScandir($this->path);
        if(!empty($this->loadFile)){
            foreach ($this->loadFile as $key=>$val){
                $this->config[$key] = require_once $val;
            }
        }
    }

    /**
     * 单例模式
     */
    public static function getInstance(array $config=[])
    {
        if(!self::$instance){
            self::$instance = new self($config);
        }
        return self::$instance;
    }


    public function get($key, $default=[])
    {
        $config = $this->config;

        if(isset($config[$key])){
            return $config[$key];
        }

        if(strpos($key, '.') == false){
            return $default;
        }

        foreach (explode('.', $key) as $segment){
            if(!is_array($config) || array_key_exists($segment, $config)){
                return $default;
            }
            $config = $config[$segment];
        }
        return $config;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->config);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        if(isset($this->config[$offset])){
            $this->config[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        if(isset($this->config[$offset])){
            unset($this->config[$offset]);
        }
    }
}