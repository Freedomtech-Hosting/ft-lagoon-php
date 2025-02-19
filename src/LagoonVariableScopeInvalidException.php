<?php namespace FreedomtechHosting\FtLagoonPhp;

use Exception;

class LagoonVariableScopeInvalidException extends Exception
{
    public function __construct($message = "Invalid variable scope", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
