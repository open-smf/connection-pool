<?php

namespace Smf\ConnectionPool;

class Connection
{
    protected $rawConnection;

    public function __construct($rawConnection)
    {
        $this->rawConnection = $rawConnection;
    }

    public function getRawConnection()
    {
        return $this->rawConnection;
    }

    public function __call($name, array $arguments)
    {
        return call_user_func_array([$this->rawConnection, $name], $arguments);
    }
}