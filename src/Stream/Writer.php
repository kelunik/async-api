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

class Writer implements WritableStream
{
    protected $resource;
    
    protected $watcher;
    
    protected $bufferSize;
    
    protected $buffer = '';
    
    public function __construct($resource, ?Watcher $watcher = null)
    {
        $this->resource = $resource;
        $this->watcher = $watcher ?? new Watcher($resource);
        
        \stream_set_blocking($resource, false);
        \stream_set_write_buffer($resource, 0);
    }
    
    public function close(?\Throwable $e = null): void
    {
        $this->watcher->close($e);
    }

    public function write(string $data): void
    {
        $retried = false;
        
        while ($data !== '') {
            if (!\is_resource($this->resource)) {
                throw new StreamClosedException('Cannot write to closed stream');
            }
            
            if (false === ($len = @\fwrite($this->resource, $data, 0xFFFF))) {
                throw new StreamException(\sprintf('Could not write to stream: %s', \error_get_last()['message'] ?? ''));
            }
            
            if ($len > 0) {
                $data = \substr($data, $len);
            } elseif (@\feof($this->resource)) {
                throw new StreamClosedException('Cannot write to closed stream');
            } else {
                $this->watcher->awaitWritable();
            }
        }
    }
}
