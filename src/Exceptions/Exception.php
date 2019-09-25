<?php
namespace Lyignore\WebsocketUpload\Exception;

class Exception extends \Exception
{
    public function report()
    {

    }

    public function render()
    {
        return response()->json([
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
        ]);
    }
}