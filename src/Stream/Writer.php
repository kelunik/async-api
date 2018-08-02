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

class Writer implements WritableStream
{
    use WriterTrait;
    
    protected $resource;
    
    protected $watcher;
    
    public function __construct($resource, ?StreamWatcher $watcher = null)
    {
        $this->resource = $resource;
        $this->watcher = $watcher ?? new StreamWatcher($resource);
        
        if (!\stream_set_blocking($resource, false)) {
            throw new \InvalidArgumentException('Cannot switch resource to non-blocking mode');
        }
        
        \stream_set_write_buffer($resource, 0);
    }
    
    public function close(?\Throwable $e = null): void
    {
        if (\is_resource($this->resource)) {
            $this->closeWriter($e);
            
            $this->resource = null;
        }
    }
}
