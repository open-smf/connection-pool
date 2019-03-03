<?php

namespace Smf\ConnectionPool;

class Connection
{
    protected $rawConnection;

    /**@var int $lastActiveTime The last active time in second */
    protected $lastActiveTime = 0;

    public function __construct($rawConnection)
    {
        $this->rawConnection = $rawConnection;
    }

    public function getRawConnection()
    {
        return $this->rawConnection;
    }

    public function updateLastActiveTime()
    {
        $this->lastActiveTime = time();
    }

    public function getLastActiveTime(): int
    {
        return $this->lastActiveTime;
    }

    public function __get(string $name)
    {
        return $this->rawConnection->{$name} ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->rawConnection->{$name});
    }

    public function __set(string $name, $value)
    {
        $this->rawConnection->{$name} = $value;
    }

    public function __unset(string $name)
    {
        unset($this->rawConnection->{$name});
    }

    public function __call(string $name, array $arguments)
    {
        return call_user_func_array([$this->rawConnection, $name], $arguments);
    }
}