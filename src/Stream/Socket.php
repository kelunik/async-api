<?php

/*
 +----------------------------------------------------------------------+
 | PHP Version 7                                                        |
 +----------------------------------------------------------------------+
 | Copyright (c) 1997-2018 The PHP Group                                |
 +----------------------------------------------------------------------+
 | This source file is subject to version 3.01 of the PHP license,      |
 | that is bundled with this package in the file LICENSE, and is        |
 | available through the world-wide-web at the following url:           |
 | http://www.php.net/license/3_01.txt                                  |
 | If you did not receive a copy of the PHP license and are unable to   |
 | obtain it through the world-wide-web, please send a note to          |
 | license@php.net so we can mail you a copy immediately.               |
 +----------------------------------------------------------------------+
 | Authors: Martin Schröder <m.schroeder2007@gmail.com>                 |
 +----------------------------------------------------------------------+
 */

namespace Concurrent\Stream;

use Concurrent\Watcher;

class Socket implements DuplexStream
{
    protected const CONNECT_FLAGS = \STREAM_CLIENT_CONNECT | \STREAM_CLIENT_ASYNC_CONNECT;
    
    protected $reader;

    protected $writer;

    protected function __construct($socket, Watcher $watcher)
    {
        $this->reader = new Reader($socket, $watcher);
        $this->writer = new Writer($socket, $watcher);
    }
    
    public static function pair(): array
    {
        return \stream_socket_pair((DIRECTORY_SEPARATOR == '\\') ? \STREAM_PF_INET : \STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
    }

    public static function streamPair(): array
    {
        list ($a, $b) = static::pair();
        
        return [
            new Socket($a, new Watcher($a)),
            new Socket($b, new Watcher($b))
        ];
    }
    
    public static function connect(string $uri): Socket
    {
        $errno = null;
        $errstr = null;
        
        $socket = @\stream_socket_client($uri, $errno, $errstr, 0, self::CONNECT_FLAGS);
        
        if ($socket === false) {
            throw new \RuntimeException(\sprintf('Failed connecting to "%s": [%s] %s', $uri, $errno, $errstr));
        }
        
        $watcher = new Watcher($socket);
        $watcher->awaitWritable();
        
        if (false === @\stream_socket_get_name($socket, true)) {
            \fclose($socket);
            
            throw new \RuntimeException(\sprintf('Connection to %s refused', $uri));
        }
        
        return new Socket($socket, $watcher);
    }

    public function close(?\Throwable $e = null): void
    {
        $this->reader->close($e);
        $this->writer->close($e);
    }

    public function read(?int $length = null): ?string
    {
        return $this->reader->read($length);
    }

    public function write(string $data): void
    {
        $this->writer->write($data);
    }
}
