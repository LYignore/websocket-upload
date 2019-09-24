<?php
namespace Lyignore\WebsocketUpload;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Lyignore\WebsocketUpload\Support\Config;
use Lyignore\WebsocketUpload\Traits\Resource;
use Lyignore\WebsocketUpload\Traits\System;

class WebsocketUpload{
    const PORT = '8000';
    protected $config;      // 总体的配置信息
    protected $server;      // swoole的实例化
    protected $table;       // 内存结构表
    protected $listern;     // 监听进程
    protected $user;

    public static $tokenChan;   // token的监听管道
    public static $discernChan; // 识别结果的监听管道

    public function __construct(array $config=[])
    {
        // 添加配置信息
        $this->config = Config::getInstance($config);

        // 实例化swoole
        $this->initSwoole();

        // 创建共享内存
        $this->createTable();

        // 创建监听进程
        $this->setListern();
    }

    public function start()
    {
        $this->server->on("open", [$this, 'wsOpen']);
        $this->server->on("message", [$this, 'wsMessage']);
        $this->server->on("close", [$this, 'wsClose']);

        $this->server->start();
    }

    /**
     * 实例化swoole
     */
    private function initSwoole($config = [])
    {
        if(!$this->server instanceof \Swoole\WebSocket\Server){
            $this->server = new \Swoole\WebSocket\Server("0.0.0.0", self::PORT);
            $config = array_merge($this->config->get('socket'), $config);
            $this->server->set([
                'worker_num'  => $config['worker_num'],
                'package_max_length' => $config['package_max_length'],
                'open_eof_check' => $config['open_eof_check'],
            ]);
        }
        return $this->server;
    }

    /**
     * 创建共享内存
     */
    public function createTable($config = [])
    {
        $config = array_merge($this->config->get('memory'), $config);
        $this->table = SharedMemory::getInstance($config);
    }

    /**
     * 创建监听进程
     */
    private function setListern($config = [])
    {
        $config = array_merge($this->config->get('listern'), $config);
        echo "开启监听{$config['port']}".PHP_EOL;
        $this->listern = $this->server->addListener($config['uri'], $config['port'], $config['type']);
        $this->listern->set(['open_http_protocol' => true]);
        $this->listern->on('request', [$this, 'listernRequest']);
        $this->listern->on("close", [$this, 'listernClose']);
    }

    /**
     * 监听到消息后的操作方法
     */
    public function listernRequest($request, $response)
    {
        $config = $this->config->get('listern');
        $getServer = $request->server;
        if($config['path_info'] == $getServer['path_info']){
            $getParamstr = $request->rawContent();
            $getParams = json_decode($getParamstr, true);
            if(isset($getParams['app_id'])){
                // 通过appid去共享内存获取对应的客户端连接标识fd
                $result = $this->table->get($getParams['app_id']);
                // 判断是否有有效的websocket连接
                $link = $this->server->exist($result['fd']);
                if($result && $link){
                    $return = Resource::discernSuccess($getParams);
                    $this->server->push($result['fd'], json_encode($return));
                }
            }else{
                echo '没有对应的APPID'.$getParams['app_id'].PHP_EOL;
            }
        }
    }

    public function listernClose()
    {
        return false;
    }


    /**
     * websocket 连接成功触发方法
     */
    public function wsOpen($server, $request)
    {
        $queryStr = $request->server['query_string'];
        parse_str($queryStr,$query);
        if(isset($query['appid'])){
            $appid = $query['appid'];
            $this->appid = $appid;
            // 查询用户信息
            $this->user = AuthUser::getUserInfo($appid, User::class);
            $fd = $request->fd;
            if(!empty($this->user))
            {
                // 把用户按照appid为键，存入共享内存
                $data = compact('fd', 'appid');
                $this->table->set($appid, $data);
                // 使用第三方获取数据在协程里通信
                self::$tokenChan = new \Swoole\Coroutine\Channel();
                self::$discernChan = new \Swoole\Coroutine\Channel();
            }else{
                $res = Resource::authError();
                $server->push($request->fd, json_encode($res));
                // 断开连接
                $server->close($request->fd);
            }
        }
    }

    /**
     * websocket 用户发送消息的回调函数
     */
    public function wsMessage($server, $frame)
    {
        $frameParams = json_decode($frame->data, true);
        if(empty($frameParams) || !isset($frameParams['result'])|| !isset($frameParams['filename'])){
            $res = Resource::typeError('No pictures uploaded');
            $server->push($frame->fd, json_encode($res));
            return false;
        }
        $imgData = $frameParams['result'];
        $imgName = $frameParams['filename'];
        $result = $this->getBufferType($imgData, $imgName);
        if(isset($result['buffer'])){
            if($this->config->get('original_name')){
                $imgId = $result['filename'];
            }else{
                //$imgId = md5($result['buffer']);
                $imgId = md5(uniqid(md5(microtime(true)),true));
            }
            $filename = public_path("image/".$imgId.".".$result['type']);
            file_put_contents($filename, $result['buffer']);

//            $uploadData['img_md5_id'] = $imgId;
//            $upload = Resource::uploadSuccess($uploadData);
//            $server->push($frame->fd, json_encode($upload));
            $resToken = $this->getToken($this->user);
            if($tokens = self::$tokenChan->pop()){
                $res['timestamp'] = time();
                // 获取签名
                $sign = AuthUser::getSigns($res, $this->user, $tokens);
                $res['token'] = $tokens;
                // 发送图片识别
                $resDiscern = AuthUser::imageRecognition($res, $sign, $filename);
            }
            if($discern = self::$discernChan->pop()){
                $return  = Resource::uploadDiscern($discern);
                // 调用外部的上传接口
                $server->push($frame->fd, json_encode($return));
            }
        }else{
            $res = Resource::typeError('No pictures uploaded');
            $server->push($frame->fd, json_encode($res));
        }
    }

    public function getBufferType($image, $filename){
        $result = [];
        if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $image, $res)){
            $type = $res[2];
            $buffer = base64_decode(str_replace($res[1], '', $image));
            $result = compact("type", "buffer", "filename");
        }
        return $result;
    }

    /**
     * 连接关闭回调函数
     */
    public function wsClose($server, $fd, $reactorId)
    {
        if(!empty($this->user)){
            $appid = $this->user->appid;
            echo $appid.PHP_EOL;
            if($this->table->exist($appid)){
                $this->table->del($appid);
            }
        }
        echo "{$fd}连接关闭".PHP_EOL;
    }

    /**
     * 获取用户token
     */
    protected function getToken($user)
    {
        $res = AuthUser::getToken($user['appid'], $user['secret']);
        return $res;
    }

    /**
     * 发送图片识别
     */
    protected function imageRecognition($data, $sign, $file)
    {
        $res = AuthUser::imageRecognition($data, $sign, $file);
        return $res;
    }

    protected function getClosure(array $data)
    {
        return function($c, $parameters = []) use($data){
            $param = array_merge($parameters, $data);
            return $c->send($param);
        };
    }

    public static function sendMessage($appid, $mess)
    {
        return AuthUser::$sendMess($appid, $mess);
    }
}