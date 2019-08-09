<?php
namespace Lyignore\WebsocketUpload;
use Lyignore\WebsocketUpload\Support\Config;
use Lyignore\WebsocketUpload\Traits\System;

class WebsocketUpload{
    public function __construct(array $config=[])
    {
        // 添加配置信息
        $this->config = Config::getInstance($config);
    }
    

    public function test()
    {
        return $this->config->get('ly');
    }
}