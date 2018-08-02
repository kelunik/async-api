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

trait WriterTrait
{
    protected $writing = false;
    
    protected function closeWriter(?\Throwable $e = null): void
    {
        $meta = @\stream_get_meta_data($this->resource);
        
        if ($meta && \strpos($meta['mode'], '+') !== false) {
            @\stream_socket_shutdown($this->resource, \STREAM_SHUT_WR);
        } else {
            @\fclose($this->resource);
        }
        
        $this->watcher->close($e);
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $data): void
    {
        $retried = false;
        
        while ($this->writing) {
            $this->watcher->awaitWritable();
        }
        
        while ($data !== '') {
            if (!\is_resource($this->resource)) {
                throw new StreamClosedException('Cannot write to closed stream');
            }
            
            if (false === ($len = @\fwrite($this->resource, $data, 0xFFFF))) {
                throw new StreamException(\sprintf('Could not write to stream: %s', \error_get_last()['message'] ?? ''));
            }
            
            if ($len > 0) {
                $data = \substr($data, $len);
                $retried = false;
            } elseif (@\feof($this->resource)) {
                throw new StreamClosedException('Cannot write to closed stream');
            } else {
                if ($retried) {
                    throw new StreamClosedException('Could not write bytes after retry, assuming broken pipe');
                }
                
                $this->writing = true;
                
                try {
                    $this->watcher->awaitWritable();
                } finally {
                    $this->writing = false;
                }
                
                $retried = true;
            }
        }
    }
}
