<?php
namespace Lyignore\WebsocketUpload;

class SharedMemory{
    const SIZE = 10;

    protected static $table;

    protected static $config = ['fd' => 'int', 'appid' => 'string'];

    protected static $instance;

    protected function __construct(array $config=[])
    {
        // 添加配置信息
        if(!empty($config)){
            self::$config = $config;
        }
        $size = pow(2, self::SIZE);
        self::$table = new \Swoole\Table($size);
    }

    // 设计单例模式生成内存结构
    public static function getInstance(array $config)
    {
        if(!self::$instance){
            self::$instance = new self($config);
            if(self::isNumArray(self::$config)){
                $configs = [];
                foreach(self::$config as $value){
                    $configs[$value] = 'string';
                }
                self::$config = $configs;
            }
            foreach (self::$config as $keys => $values){
                if($values == 'int'){
                    self::$table->column($keys, \Swoole\Table::TYPE_INT, 8);
                }elseif($values == 'float'){
                    self::$table->column($keys, \Swoole\Table::TYPE_FLOAT, 8);
                }else{
                    self::$table->column($keys, \Swoole\Table::TYPE_STRING, 255);
                }
            }
            self::$table->create();
        }
        return self::$instance;
    }

    /*
     * 判断是关联数组还是数值数组
     * return true 为数值数组，否则为关联数组
     */
    public static function isNumArray(array $arr)
    {
        $index = 0;
        if(empty($arr)) return true;
        foreach (array_keys($arr) as $key){
            if($index++ != $key) return false;
        }
        return true;
    }

    public function set($keys, $data)
    {
        $check = array_keys(self::$config);
        foreach ($check as $value){
            if(!isset($data[$value])){
                $data[$value] = "";
            }
        }
        return self::$table->set($keys, $data);
    }

    public function get($keys)
    {
        return self::$table->get($keys);
    }

    public function del($keys)
    {
        return self::$table->del($keys);
    }

    public function exist($keys)
    {
        return self::$table->exist($keys);
    }
}