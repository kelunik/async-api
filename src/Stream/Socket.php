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

use Concurrent\StreamWatcher;

class Socket implements DuplexStream
{
    use ReaderTrait;
    use WriterTrait;
    
    protected const CONNECT_FLAGS = \STREAM_CLIENT_CONNECT | \STREAM_CLIENT_ASYNC_CONNECT;
    
    protected $resource;

    protected $watcher;

    protected function __construct($socket, StreamWatcher $watcher, int $bufferSize = 0x8000)
    {
        $this->resource = $socket;
        $this->watcher = $watcher;
        $this->bufferSize = $bufferSize;
        
        if (!\stream_set_blocking($socket, false)) {
            throw new \InvalidArgumentException('Cannot switch resource to non-blocking mode');
        }
        
        \stream_set_read_buffer($socket, 0);
        \stream_set_write_buffer($socket, 0);
    }
    
    public static function pair(): array
    {
        return \stream_socket_pair((DIRECTORY_SEPARATOR == '\\') ? \STREAM_PF_INET : \STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
    }

    public static function streamPair(): array
    {
        return \array_map(function ($a) {
            return new Socket($a, new StreamWatcher($a));
        }, static::pair());
    }
    
    /**
     * Stablish an unencrypted socket connection to the given URL (tcp:// or unix://).
     * 
     * WARNING: This requires a DNS lookup if you pass a hostname instead of an IP address, non-blocking DNS
     * is not available yet!
     */
    public static function connect(string $uri): Socket
    {
        $errno = null;
        $errstr = null;
        
        $socket = @\stream_socket_client($uri, $errno, $errstr, 0, self::CONNECT_FLAGS);
        
        if ($socket === false) {
            throw new \RuntimeException(\sprintf('Failed connecting to "%s": [%s] %s', $uri, $errno, $errstr));
        }
        
        $watcher = new StreamWatcher($socket);
        $watcher->awaitWritable();
        
        if (false === @\stream_socket_get_name($socket, true)) {
            \fclose($socket);
            
            throw new \RuntimeException(\sprintf('Connection to %s refused', $uri));
        }
        
        return new Socket($socket, $watcher);
    }

    /**
     * {@inheritdoc}
     */
    public function close(?\Throwable $e = null): void
    {
        if (\is_resource($this->resource)) {
            $this->closeReader($e);
            $this->closeWriter($e);
            
            $this->resource = null;
        }
    }
}
