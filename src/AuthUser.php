<?php
namespace Lyignore\WebsocketUpload;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;

class AuthUser{
    public static $keyValue='appid';

    public static $user;

    public static $token;
    
    public static function getUserInfo($appid, $model)
    {
        $key = self::$keyValue;
        self::$user = $model::where($key, $appid)->first();
        return self::$user;
    }

    /**
     * 通过appid和secret获取token
     */
    public static function getToken($appid, $secret)
    {
        //判断redis中是否有值，没有值去python接口中获取token
        $key = 'tokens:'.$appid;
        if(Redis::exists($key)){
            $token = Redis::get($key);
            if(!empty(WebsocketUpload::$tokenChan)){
                WebsocketUpload::$tokenChan->push($token);
            }else{
                return false;
            }
        }else{
            $bodys = compact('appid', 'secret');
            self::thirdGetToken($bodys);
        }
        return true;
    }

    /**
     * 通过第三方获取token
     */
    protected static function thirdGetToken($user)
    {
        $website = explode(':', config('swoole.third_uri.token_uri'));
        $cli = new \Swoole\Coroutine\Http\Client($website[0], $website[1]??80);
        $cli->setHeaders(
            [
                'Host' => $website[0]
            ]
        );
        $cli->set(['timeout' => -1]);
        //$params = compact('appid', 'secret');
        $params['appid'] = 'K0Es84LdL/ugr5gwWe09n5QLL/Y89ymt';
        $params['secret']= 'DzpJyENy+xpoIp+1TKuip22bCLBrLWE6';
        $cli->post('/token', $params);
        $body = json_decode($cli->body,true);
        if($body['return_code'] == 200 && !empty(WebsocketUpload::$tokenChan)){
            WebsocketUpload::$tokenChan->push($body['data']['token']);
            Redis::set($params['appid'], $body['data']['token']);
            Redis::expire($params['appid'], 300);
        }
        $cli->close();
    }
    
    /**
     * 第三方识别图片
     */
    public static function imageRecognition($data, $sign, $file)
    {
        $website = explode(':', config('swoole.third_uri.discern_uri'));
        $cli = new \Swoole\Coroutine\Http\Client($website[0], $website[1]??80);
        $cli->setHeaders(
            [
                'Host' => $website[0],
                'sign' => $sign,
            ]
        );
        $cli->set(['timeout' => -1]);
        $cli->addFile($file, 'file');
        $cli->post('/api/v0/recognition/multi-type-bill', $data);
        // $cli->post('/api/v0/loadingAdvert/mobile', $data);
        // 兼容sanic的框架报错问题
        $str_body = str_replace("{\"return_code\":500,\"return_msg\":\"\u670d\u52a1\u5668\u7ef4\u62a4\u4e2d\",\"data\":\"\"}", "", $cli->body);
        $body = json_decode($str_body,true);
        if($body['return_code'] == 200 && !empty(WebsocketUpload::$discernChan)){
            WebsocketUpload::$discernChan->push($body['data']);
        }
        $cli->close();
    }

    public static function getSigns($params = [], $user, $token){
        ksort($params);
        $str = '';
        foreach ($params as $key=>$val){
            $str.=$key.$val;
        }
        $sign = md5($token.$str);
        return strtoupper($sign);
    }

    public function sendMessage($appid, $mess)
    {
        
    }
}