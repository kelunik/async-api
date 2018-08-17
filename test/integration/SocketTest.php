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
use function Concurrent\gethostbyname;
use Concurrent\Network\TcpSocket;

class SocketTest extends AsyncTestCase
{
    public function provideTargets()
    {
        yield [
            'tcp://www.google.com:80',
            false
        ];
        
        yield [
            'www.google.com:80',
            true
        ];
        
        yield [
            'tls://www.google.com:443',
            false
        ];
    }

    /**
     * @dataProvider provideTargets
     */
    public function testSocket(string $url, bool $native)
    {
        if ($native) {
            $socket = TcpSocket::connect(...\explode(':', $url, 2));
        } else {
            $socket = Socket::connect($url);
        }
        
        try {
            $socket->write(implode("\r\n", [
                'GET / HTTP/1.0',
                'Host: www.google.com',
                'Connection: close'
            ]) . "\r\n\r\n");
            
            $buffer = '';
            
            while (null !== ($chunk = $socket->read())) {
                $buffer .= $chunk;
            }
            
            list ($headers, $data) = \explode("\r\n\r\n", $buffer);
            $headers = \explode("\r\n", $headers);
            $line = \array_shift($headers);
            $m = null;
            
            $this->assertEquals(1, \preg_match("'^HTTP/(?<version>1\\.[01])\s+(?<status>[0-9]{3})(.*)$'i", $line, $m));
            $this->assertEquals('1.0', $m['version']);
            $this->assertEquals(200, $m['status']);
        } finally {
            $socket->close();
        }
    }
}
