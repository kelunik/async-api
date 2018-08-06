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
use Concurrent\Task;
use Concurrent\Timer;

class StreamTest extends AsyncTestCase
{
    public function testTimerBasedWrites()
    {
        $messages = str_split($message = 'Hello Socket :)', 4);
        
        list ($a, $b) = Socket::pair();
        
        Task::async(function () use ($a, $messages) {
            $timer = new Timer(150);
            $i = 0;
            
            try {
                while (isset($messages[$i])) {
                    $timer->awaitTimeout();
                    
                    fwrite($a, $messages[$i++]);
                }
            } finally {
                fclose($a);
            }
        });
        
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
    
    public function provideSendReceiveSettings()
    {
        yield [70, false, false];
        yield [70, true, false];
        yield [70, false, true];
        yield [70, true, true];
        yield [7000, false, false];
        yield [8192, false, false];
        yield [8000 * 100, false, false];
        yield [8000 * 100, true, false];
        yield [8000 * 100, false, true];
        yield [8000 * 100, true, true];
    }

    /**
     * @dataProvider provideSendReceiveSettings
     */
    public function testSenderAndReceiver(int $size, bool $delayedSend, bool $delayedReceive)
    {
        $message = str_repeat('.', $size);
        $received = '';
        
        list ($a, $b) = Socket::streamPair();
        
        $t = Task::async(function (WritableStream $socket) use ($message, $delayedSend) {
            $timer = new Timer(10);
            
            try {
                foreach (str_split($message, 7000) as $chunk) {
                    if ($delayedSend) {
                        $timer->awaitTimeout();
                    }
                    
                    $socket->write($chunk);
                }
            } finally {
                $socket->close();
            }
            
            return 'DONE';
        }, $a);
        
        $timer = new Timer(10);
        
        try {
            if ($delayedReceive) {
                $timer->awaitTimeout();
            }
            
            while (null !== ($chunk = $b->read())) {
                if ($delayedReceive) {
                    $timer->awaitTimeout();
                }
                
                $received .= $chunk;
            }
        } finally {
            $b->close();
        }
        
        $this->assertEquals('DONE', Task::await($t));
        $this->assertEquals($message, $received);
    }
}
