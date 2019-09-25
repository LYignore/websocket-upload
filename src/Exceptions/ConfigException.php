<?php
namespace Lyignore\WebsocketUpload\Exception;

use Throwable;

class ConfigException extends Exception
{
    public $raw;
    public function __construct($message = "", $raw = "", $code = 0, Throwable $previous = null)
    {
        $this->raw = $raw;

        parent::__construct($message, $code, $previous);
    }
}