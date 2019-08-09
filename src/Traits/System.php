<?php
namespace Lyignore\WebsocketUpload\Traits;
trait System{
    protected function myScandir($dir='./Config'){
        $files = array();
        //检测文件夹是否存在
        if(is_dir($dir)){
            //打开目录
            if($handle = opendir($dir)){
                while(($file = readdir($handle)) !== false){
                    if($file!='.' && $file!='..'){
                        $path = $dir."/".$file;
                        $fileInfo = pathinfo($path);
                        $keys = strtolower($fileInfo['filename']);
                        $files[$keys] = $path;
                    }
                }
                closedir($handle);
                return $files;
            }else{
                throw new \Exception('Insufficient permissions or an empty configuration file');
            }
        }else{
            throw new \Exception('The configuration folder does not exist');
        }
    }
}