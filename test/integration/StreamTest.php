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
use function Concurrent\delay;

class StreamTest extends AsyncTestCase
{
    public function testTimerBasedWrites()
    {
        $messages = str_split($message = 'Hello Socket :)', 4);
        
        list ($a, $b) = Socket::pair();
        
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
            try {
                foreach (str_split($message, 7000) as $chunk) {
                    if ($delayedSend) {
                        Task::await(delay(random_int(5, 35)));
                    }
                    
                    $socket->write($chunk);
                }
            } finally {
                $socket->close();
            }
            
            return 'DONE';
        }, $a);
        
        try {
            if ($delayedReceive) {
                Task::await(delay(random_int(5, 35)));
            }
            
            while (null !== ($chunk = $b->read())) {
                if ($delayedReceive) {
                    Task::await(delay(random_int(5, 35)));
                }
                
                $received .= $chunk;
            }
        } finally {
            $b->close();
        }
        
        $this->assertEquals('DONE', Task::await($t));
        $this->assertEquals($message, $received);
    }
    
//     public function testSocket()
//     {
//         $socket = Socket::connect('tcp://google.com:80');
        
//         try {
//             $socket->write(implode("\r\n", [
//                 'GET / HTTP/1.0',
//                 'Host: www.google.com',
//                 'Connection: close'
//             ]) . "\r\n\r\n");
            
//             var_dump($socket->read());
//         } finally {
//             $socket->close();
//         }
//     }
}
