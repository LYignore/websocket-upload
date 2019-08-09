<?php
//require_once __DIR__.'/vendor/autoload.php';
//1.创建websocket服务器对象
//$serv =  new swoole_websocket_server('0.0.0.0',8000);
////2.监听websocket连接事件
//$serv->on('open',function(swoole_websocket_server $server, swoole_websocket_frame $frame){
//    $server->push($frame->fd, "hello, welcome lyignore \n");
//});
////3.当客户端给ws服务器发送消息时回应
//$serv->on('message',function(swoole_websocket_server $server, swoole_websocket_frame $frame){
//    //2.1获取客户端传过来的值做逻辑处理
//    $receive_data = $frame->data;
//    $send_data = (int)$receive_data+20;
//    //2.2把结果返回给客户端
//    $server->push($frame->fd, $send_data, 1, true);
//
//});
////4.监听客户端关闭事件
//$serv->on('close', function(swoole_websocket_server $server, swoole_websocket_frame $frame){
//    echo "client-{$frame->fd} is closed\n";
//});
//
////5.添加request,对所有websocket连接的用户进行推送消息
//$serv->on('request', function ($request, $response) {
//    // 接收http请求从get获取message参数的值，给用户推送
//    global $serv;
//    $get = $request->get;
//
//    //$this->server->connections; //遍历所有websocket连接用户的fd，给所有用户推送
//    foreach ($serv->connections as $fd) {
//        //$mess = 'ignore';
//        $serv->push($fd, json_encode($get));
//    }
//});
////6.启动ws服务器
//$serv->start();
require_once "UploadImageComponent.php";
class WebsocketTest{

    private $server;

    private $table;

    private $mq;

    private $process;

    private $listern;

    // 设置内存存储格式
    const MEM_TYPE_INT = [\Swoole\Table::TYPE_INT, 8];
    const MEM_TYPE_STRING = [\Swoole\Table::TYPE_STRING, 32];
    const MEM_TYPE_FLOAT = [\Swoole\Table::TYPE_FLOAT, 8];

    public function __construct(){
        $this->server = new Swoole\WebSocket\Server("0.0.0.0", 8000);
        $this->server->set([
            //'task_worker_num' => 8,
            'worker_num'  => 8,
            'package_max_length' => 40 * 1024 * 1024,
            'open_eof_check' => true,
            //'buffer_output_size' => 4 * 1024 * 1024
        ]);
        // 创建共享内存
        $this->createTable();
        // 监听同步端口
        $this->setListern();
    }

    public function start()
    {
        // $this->setProcess();
        // $this->server->on("task", [$this, 'onTask']);

        $this->server->on("open", [$this, 'wxOpen']);
        $this->server->on("message", [$this, 'wsMessage']);
        // $this->server->on("request", [$this, 'wsRequest']);
        $this->server->on("close", [$this, 'wsClose']);
        $this->server->start();
    }

    /**
     * 创建共享内存表，存储连接标识 fd,连接用户的APPID
     * @param 创建的内存名称字段
     */
    private function createTable($config = null, $bitData = 10)
    {
        $size = pow(2,$bitData);
        $table = new \Swoole\Table($size);
        if(empty($config)){
            $config = ['fd'=>self::MEM_TYPE_FLOAT, 'appid'=>self::MEM_TYPE_STRING];
        }
        foreach($config as $key=>$val){
            $table->column($key, $val[0], $val[1]);
        }
        if($table->create()){
            $this->table = $table;
        }else{
            throw new Exception('run out of memory');
        }
    }

    /**
     * 设置监听端口
     */
    private function setListern()
    {
        echo "开启监听8081端口\n";

        // 开启子程序监听8081端口
        $this->listern = $this->server->addListener('0.0.0.0', '8081', SWOOLE_SOCK_TCP);
        // 监听8081访问的请求体回调
        $this->listern->on('Request', [$this, 'listernRequest']);
    }

    /**
     * 建立通讯管道，当有图片处理识别完成后，识别信息入管道，向指定用户推送完成消息
     */
    public function setProcess()
    {
        $server = $this->server;
        $this->process = new Swoole\Process(function($process)use($server){
            while(true){
                $msg = $process->read();
                foreach($this->server->connections as $conn){
                    $server->send($conn, $msg);
                }
            }
        });
        $this->server->addProcess($this->process);
    }

    /**
     * websocket 的连接成功触发事件方法
     */
    public function wxOpen(swoole_websocket_server $server, $request)
    {
        $message['from'] = 'server';
        $message['to'] = $request->fd;
        $message['messsage'] = '连接成功';
        $server->push($request->fd, json_encode($message));
        $data = $this->formalMemoryData($request->fd, "12345");
        $this->table->set($request->fd, $data);
//        foreach ($this->table as $u){
//            var_dump($u);
//        }
//        $this->getRabbitMQ();
//        $this->initConsumer();
    }

    /**
     * 格式化共享内存行格式
     */
    private function formalMemoryData($fd, $appid)
    {
        return ['fd'=>(int)$fd, 'appid' =>(string)$appid];
    }

    /**
     * websocket的接受消息触发事件方法
     */
    public function wsMessage(Swoole\WebSocket\Server $server, $frame)
    {
        $result = $this->getBufferType($frame->data);
        $imgId = md5($result['buffer']);
        $filename = "imgs/".$imgId.".".$result['type'];
        file_put_contents($filename, $result['buffer']);
        $res['client_id'] = $frame->fd;
        $res['id']   = $imgId;
        $res['type'] = $result['type'];
        $res['path'] = $filename;
        $pushMess = $this->addMessToMQ($res);
        $server->push($frame->fd, json_encode($pushMess));
    }

    /**
     * websocket的request方法
     */
    public function wsRequest($request, $response)
    {
        $get = $request->get;
        foreach($this->table as $fd){
            if($this->server->exist($fd['fd'])){
                $this->server->push($fd['fd'], json_encode($get));
            }
        }
        var_dump($get);
        return $response->end(json_encode($get));
    }

    /**
     * websocket的close方法
     */
    public function wsClose(Swoole\WebSocket\Server $server, $frame)
    {
        var_dump($frame);
        // $this->table->del($frame);
    }

    public function getBufferType($image){
        $result = [];
        if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $image, $res)){
            $type = $res[2];
            $buffer = base64_decode(str_replace($res[1], '', $image));
            $result = compact("type", "buffer");
        }
        return $result;
    }

    public function getRabbitMQ()
    {
        if(!$this->mq instanceof UploadImageComponent){
            $objRabbitMQ = \UploadImageComponent::getInstance();
            $this->mq = $objRabbitMQ;
        }
        return $this->mq;
    }

    public function getMessageFromMQ($strQueue = 'test_send_mail')
    {
        if(!$this->mq instanceof UploadImageComponent){
            $this->getRabbitMQ();
        }
        $this->mq->get($strQueue, function($envlop, $queue){
            var_dump($envlop->getBody());
        });
    }

    public function addMessToMQ($aBody)
    {
        if(!$this->mq instanceof UploadImageComponent){
            $this->getRabbitMQ();
        }
        if(is_array($aBody)){
            $aBody = json_encode($aBody);
        }
        $result = $this->mq->add($aBody, null, 15641565091);
        return $result;
    }

    protected function initConsumer()
    {
//        if(!$this->mq instanceof UploadImageComponent){
//            $this->getRabbitMQ();
//        }
//        Swoole\Timer::tick(1000, function($timerId, $obj){
//            $this->mq->get("15641565091", function($envlop, $queue)use($timerId, $obj){
//                //$queue->ack($envlop->getDeliveryTag());
//                $obj->getMess($envlop->getBody());
//            },false);
//        }, $this);
        //$this->server->on("close", [$this, 'wsClose']);
        $this->server->task($this->mq);

    }

    /**
     * 分支进程，轮训挂起，充当队列消费者角色
     */
    public function onTask($serv, $task_id, $from_id, $data)
    {
        echo "New AsyncTask[id=$task_id]".PHP_EOL;
        while (true){
            if($data instanceof UploadImageComponent){
                $data = \UploadImageComponent::getInstance();
            }
            $data->get("15641565091", function($envlop, $queue){
                //$queue->ack($envlop->getDeliveryTag());
                var_dump($envlop->getBody());
            },false);
        }
    }

    /**
     * 监听端口的request回调
     */
    public function listernRequest($request, $response)
    {
        $getParams = $request->get;
        if(isset($getParams['appid'])){
            foreach($this->table as $fd){
                if($this->server->exist($fd['fd'])){
                    $this->server->push($fd['fd'], json_encode($getParams));
                }
            }
        }

        return $response->end(json_encode($this->table));
    }
}
$ws = new WebsocketTest();
$ws->start();