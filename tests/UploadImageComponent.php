<?php

class UploadImageComponent
{
    private static $instance = null;

    private $connection;

    private $channel;

    private $exchangeName = 'ly.uploadimg.direct';

    private $exchange;

    private $queue;

    private $queueName = 'ly.uploadimg.';

    private $message;

    private $routeKey = 'uploadimg';

    private static $types = [AMQP_EX_TYPE_DIRECT, AMQP_EX_TYPE_FANOUT, AMQP_EX_TYPE_TOPIC, AMQP_EX_TYPE_HEADERS];

    public function __construct()
    {
        $connection = new \AMQPConnection([
            'host'  => '192.168.2.183',
            'port'  => '5672',
            'vhost' => '/',
            'login' => 'invoice',
            'password' => 'glp4worD'
        ]);
        if($connection->connect()){
            $this->connection = $connection;
        }else{
            throw new \Exception('Connection to rabbitmq failed');
        }
    }

    private function __clone()
    {
    }

    public function __destruct()
    {
        $this->channel->close();

        $this->instance = null;
    }

    private function getChannel()
    {
        if(!$this->channel instanceof \AMQPChannel){
            $this->channel = new \AMQPChannel($this->connection);
        }
        return $this->channel;
    }

    /**
     *  获取单例实例
     */
    public static function getInstance()
    {
        if(!self::$instance instanceof self){
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * 外部对象调用，队列添加信息
     */
    public function add($aBody, $strExchange=null, $strQueue=null, $strType=null)
    {
        if(is_null($strType)){
            $strType = self::$types[0];
        }
        try{
            // 声明交换机
            $this->declareExchange($strExchange, $strType);
            // 声明队列
            $queueName = $this->declareQueue($strQueue);
            $res['mess'] = $aBody;
            $res['queue_id'] = $queueName;
            // 发送消息入队列
            $this->injectMessage($aBody);
            return [
                'status'    => 'success',
                'status_code' => 200,
                'data'   => $res
            ];
        }catch(\Exception $e){
            return [
                'status'    => 'error',
                'status_code' => 400,
                'message'   => 'declare exchange fail'
            ];
        }
    }

    /**
     * 外部对象调用，队列获取信息
     */
    public function get($strQueue, $callback = null, $auto_ack = true)
    {
        $this->declareQueue($strQueue);
        $queue = $this->queue;
        $queue->consume(function($envelop, $queue)use($callback, $auto_ack){
            $res = $callback($envelop, $queue);
            if($auto_ack){
                $queue->ack($envelop->getDeliveryTag());
            }
            return $res;
        });
    }

    /**
     * 声明交换机
     */
    private function declareExchange($strExchange = null, $type=null)
    {
        $channel = $this->getChannel();

        $exchange = new \AMQPExchange($channel);

        $strExchange = $strExchange?:$this->exchangeName;
        $type = $type?:self::$types[0];

        $exchange->setName($strExchange);
        $exchange->setType($type);
        $exchange->setFlags(AMQP_AUTODELETE);
        $exchange->declareExchange();
        $this->exchange = $exchange;
        return $exchange;
    }

    /**
     * 声明队列
     */
    private function declareQueue($strQueue=null)
    {
        $channel = $this->getChannel();
        $queue = new \AMQPQueue($channel);
        if(!is_null($strQueue)) {
            $queueName = $this->queueName . $strQueue;
        }else{
            $queueName = $this->queueName . rand(1000, 9999);
        }
        $queue->setName($queueName);
        $queue->declareQueue();

        if(!$this->exchange instanceof \AMQPExchange){
            $this->exchange = $this->declareExchange($this->exchangeName);
        }
        $exchangeName = $this->exchange->getName();

        $queue->bind($exchangeName, $this->routeKey);
        $this->queue = $queue;
        return $queue->getName();
    }

    /**
     * 信息入队列
     */
    public function injectMessage($aBody, $routeKey=null)
    {
        $routeKey = $routeKey?:$this->routeKey;
        if(!$this->exchange instanceof \AMQPExchange){
            $this->exchange = $this->declareExchange();
        }
        $this->message = $aBody;
        $this->exchange->publish($aBody, $routeKey);
    }
}