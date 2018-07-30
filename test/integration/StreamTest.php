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

use Concurrent\AsyncTestCase;
use Concurrent\Timer;

class StreamTest extends AsyncTestCase
{
    protected function socketPair(): array
    {
        return \stream_socket_pair((DIRECTORY_SEPARATOR == '\\') ? \STREAM_PF_INET : \STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
    }
    
    public function testTimerBasedWrites()
    {
        $messages = str_split($message = 'Hello Socket :)', 4);
        
        list ($a, $b) = $this->socketPair();
        
        $timer = new Timer(static function (Timer $timer) use ($a, $messages) {
            static $i = 0;
            
            fwrite($a, $messages[$i++]);
            
            if (!isset($messages[$i])) {
                fclose($a);
                
                $timer->stop();
            }
        });
        
        $timer->start(150, true);
        
        $reader = new Reader($b);
        $received = '';
        
        try {
            while (null !== ($chunk = $reader->read())) {
                $received .= $chunk;
            }
        } finally {
            $reader->close();
        }
        
        $this->assertEquals($message, $received);
    }
}
