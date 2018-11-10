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

namespace Concurrent
{
    /**
     * Union of all awaitable objects (Deferred and Task) being used for type-hinting.
     */
    interface Awaitable { }

    /**
     * Provides access to the logical execution context.
     */
    final class Context
    {
        /**
         * Context cannot be created in userland.
         */
        private function __construct() { }

        /**
         * Derives a new context with a value bound to the given context var.
         */
        public function with(ContextVar $var, $value): Context { }

        /**
         * Automatically cancel the context after the given number of milliseconds have passed.
         */
        public function withTimeout(int $milliseconds): Context { }

        /**
         * Create a context that is shielded from cancellation.
         */
        public function shield(): Context { }

        /**
         * Get a cancellation token for this context.
         */
        public function token(): CancellationToken { }

        /**
         * Create a background (unreferenced) context.
         * 
         * The context will automatically be shielded from cancellation!
         */
        public function background(): Context { }

        /**
         * Enables the context for the duration of the callback invocation, returns the
         * value returned from the callback.
         * 
         * Note: It is safe to use await in the callback.
         */
        public function run(callable $callback, ...$args) { }

        /**
         * Lookup the current logical execution context.
         */
        public static function current(): Context { }
    }

    /**
     * Contextual variable being used to bind and access variables in a context.
     */
    final class ContextVar
    {
        /**
         * Retrieve the value bound to the variable from the given context, will
         * return NULL if no value is set for the variable.
         * 
         * Note: Uses the current context (Context::current()) if no context is given.
         */
        public function get(?Context $context = null) { }
    }

    /**
     * Provides access to a context that can be cancelled using the handler.
     */
    final class CancellationHandler
    {
        /**
         * Derives the cancellable context from the given context (current context by default).
         */
        public function __construct(?Context $context = null) { }

        /**
         * Get the cancellable context.
         */
        public function context(): Context { }

        /**
         * Cancel the managed context, the given error will be set as previous error for the cancellation exception.
         */
        public function cancel(?\Throwable $e = null): void { }
    }

    /**
     * Provides an API to check if a context has been cancelled.
     */
    final class CancellationToken
    {
        /**
         * Check if the context has been cancelled yet.
         */
        public function isCancelled(): bool { }

        /**
         * Re-throw the error being used to cancel the context (does nothing if the context is alive).
         */
        public function throwIfCancelled(): void { }
    }

    /**
     * A deferred represents an async operation that may not have completed yet.
     * 
     * It exposes an awaitable to be consumed by other components and provides an API
     * to resolve or fail the awaitable at any time.
     */
    final class Deferred
    {
        /**
         * Is used to enable support for cancellation. The callback will receive the deferred object
         * and the cancellation error as arguments.
         */
        public function __construct(callable $cancel = null) { }

        /**
         * Provides an awaitable object that can be resolved or failed by the deferred.
         */
        public function awaitable(): Awaitable { }

        /**
         * Resolves the deferred with the given value if it has not been resolved yet.
         */
        public function resolve($val = null): void { }

        /**
         * Fails the deferred with the given error if it has not been resolved yet.
         */
        public function fail(\Throwable $e): void { }

        /**
         * Creates resolved awaitable from the given value.
         */
        public static function value($val = null): Awaitable { }

        /**
         * Creates a failed awaitable from the given error.
         */
        public static function error(\Throwable $e): Awaitable { }

        /**
         * Combines multiple (at least one) awaitables into a single awaitable. Input must be an array of
         * awaitable objects. The continuation is callled with five arguments:
         * 
         * - Deferred: Controls the result awaitable, must be resolved or failed from the callback.
         * - bool: Is true when this is the last call to the callback.
         * - mixed: Holds the key of the processed awaitable in the input array.
         * - ?Throwable: Error if the awaitable failed, NULL otherwise.
         * - ?mixed: Result provided by the awaitable, NULL otherwise.
         */
        public static function combine(array $awaitables, callable $continuation): Awaitable { }

        /**
         * Applies a transformation callback to the outcome of the awaited operation.
         * 
         * The callback receives a Throwable as first argument if the awaited operation failed. It will receive NULL
         * as first argument and the result value as second argument of the operation was successful.
         * 
         * Returning a value from the callback will resolve the result awaitable with the returned value. Throwing an
         * error from the callback will fail the result awaitable with the thrown error.
         */
        public static function transform(Awaitable $awaitable, callable $transform): Awaitable { }
    }

    /**
     * A task is a fiber-based, concurrent VM execution, that can be paused and resumed.
     */
    final class Task implements Awaitable
    {
        /**
         * Task cannot be created in userland.
         */
        private function __construct() { }

        /**
         * Check if the current execution is running in an async task.
         */
        public static function isRunning(): bool { }

        /**
         * Creates a task that will run the given callback on a task scheduler.
         */
        public static function async(callable $callback, ...$args): Task { }

        /**
         * Creates a task within the given context that will run the given callback on a task scheduler.
         */
        public static function asyncWithContext(Context $context, callable $callback, ...$args): Task { }

        /**
         * Awaits the resolution of the given awaitable.
         * 
         * The current task will be suspended until the input awaitable resolves or is failed.
         * 
         * @throws \Throwable Depends on the awaited operation.
         */
        public static function await(Awaitable $awaitable) { }
    }

    /**
     * Provides scheduling and execution of async tasks.
     */
    final class TaskScheduler
    {
        /**
         * Task scheduler cannot be created in userland.
         */
        private function __construct() { }

        /**
         * Runs the given callback as a task in an isolated scheduler and returns the result.
         * 
         * The inspect callback will be called after the callback-based task completes. It will receive an array
         * of arrays containing information about every unfinished task.
         */
        public static function run(callable $callback, ?callable $inspect = null) { }

        /**
         * Runs the given callback as a task in the given context in an isolated scheduler and returns the result.
         *
         * The inspect callback will be called after the callback-based task completes. It will receive an array
         * of arrays containing information about every unfinished task.
         */
        public static function runWithContext(Context $context, callable $callback, ?callable $inspect = null) { }
    }

    /**
     * Provides timers and future ticks backed by the internal event loop.
     */
    final class Timer
    {
        /**
         * Create a new timer with the given delay (in milliseconds).
         */
        public function __construct(int $milliseconds) { }

        /**
         * Stops the timer if it is running, this will dispose of all pending await operations.
         * 
         * After a call to this method no further timeout operations will be possible.
         */
        public function close(?\Throwable $e = null): void { }

        /**
         * Suspends the current task until the timer fires.
         */
        public function awaitTimeout(): void { }
    }

    /**
     * Provides non-blocking IO integration.
     */
    final class StreamWatcher
    {
        /**
         * Create a stream watcher for the given resource.
         * 
         * @param resource $resource PHP stream or socket resource.
         */
        public function __construct($resource) { }

        /**
         * Close the watcher, this will throw an error into all tasks waiting for readablility / writability.
         * 
         * After a call to this method not further read / write operations can be awaited.
         * 
         * @param \Throwable $e Optional reason that caused closing the watcher.
         */
        public function close(?\Throwable $e = null): void { }

        /**
         * Suspends the current task until the watched resource is reported as readable.
         */
        public function awaitReadable(): void { }

        /**
         * Suspends the current task until the watched resource is reported as writable.
         */
        public function awaitWritable(): void { }
    }

    /**
     * Provides UNIX signal and Windows CTRL + C handling.
     */
    final class SignalWatcher
    {
        /**
         * Console window has been closed.
         */
        public const SIGHUP = 1;

        /**
         * Received CTRL + C keyboard interrupt.
         */
        public const SIGINT = 2;

        public const SIGQUIT = 3;

        public const SIGKILL = 9;

        public const SIGTERM = 15;

        public const SIGUSR1 = 10;

        public const SIGUSR2 = 12;

        /**
         * Create a watcher for the given signal number.
         */
        public function __construct(int $signum) { }

        /**
         * Close the watcher, this will throw an error into all tasks waiting for the signal.
         * 
         * After a call to this method not further signals can be awaited using this watcher.
         * 
         * @param \Throwable $e Optional reason that caused closing the watcher.
         */
        public function close(?\Throwable $e = null): void { }

        /**
         * Suspend the current task until the signal is caught.
         */
        public function awaitSignal(): void { }

        /**
         * Check handling the given signal is supported by the OS.
         */
        public static function isSupported(int $signum): bool { }
    }

    /**
     * Exposes a callback-based fiber that requires explicit scheduling in userland.
     */
    final class Fiber
    {
        /**
         * The fiber has has been created but not started yet.
         */
        public const STATUS_INIT = 0;

        /**
         * The fiber is waiting at a yield.
         */
        public const STATUS_SUSPENDED = 1;

        /**
         * The fiber is currently running.
         */
        public const STATUS_RUNNING = 2;

        /**
         * The fiber has successfully completed it's work.
         */
        public const STATUS_FINISHED = 64;

        /**
         * The fiber has exited with an error.
         */
        public const STATUS_FAILED = 65;

        /**
         * Creates a new fiber from the given callback.
         */
        public function __construct(callable $callback, ?int $stack_size = null) { }

        /**
         * Returns the current status of the fiber object (see status class constants).
         */
        public function status(): int { }

        /**
         * Starts the fiber, arguments are passed to the callback specified in the constructor.
         */
        public function start(...$args) { }

        /**
         * Resume the fiber with the given value at the latest call to yield.
         */
        public function resume($val = null) { }

        /**
         * Resume the fiber with the given error at the latest call to yield.
         */
        public function throw(\Throwable $e) { }

        /**
         * Check if the current execution is run in a fiber.
         */
        public static function isRunning(): bool { }

        /**
         * Get a short description of the native fiber backend being used.
         */
        public static function backend(): string { }

        /**
         * Suspend the current fiber until it is resumed again.
         */
        public static function yield($val = null) { }
    }
}

namespace Concurrent\Stream
{
    /**
     * Provides read access to a stream of bytes.
     */
    interface ReadableStream
    {
        /**
         * Close the stream, will fail all pending and future reads.
         * 
         * @param \Throwable $e Reason for close.
         */
        public function close(?\Throwable $e = null): void;

        /**
         * Read a chunk of data from the stream.
         * 
         * @param int $length Maximum number of bytes to be read (might return fewer bytes).
         * @return string|NULL Next chunk of data or null if EOF is reached.
         * 
         * @throws StreamClosedException When the stream ahs been closed before or during the read operation.
         * @throws PendingReadException When another read has not completed yet.
         */
        public function read(?int $length = null): ?string;
    }

    /**
     * Provides write access to a stream of bytes.
     */
    interface WritableStream
    {
        /**
         * Close the stream, will fail all pending and future writes.
         * 
         * @param \Throwable $e Reason for close.
         */
        public function close(?\Throwable $e = null): void;

        /**
         * Write a chunk of data to the stream.
         */
        public function write(string $data): void;
    }
    
    /**
     * Union of read and write stream.
     */
    interface DuplexStream extends ReadableStream, WritableStream
    {
        /**
         * Get a readable stream backed by the duplex stream.
         */
        public function readStream(): ReadableStream;
        
        /**
         * Get a writable stream backed by the duplex stream.
         */
        public function writeStream(): WritableStream;
    }
    
    /**
     * Is thrown due to an error during stream processing.
     */
    class StreamException extends \Exception { }
    
    /**
     * Is thrown when an operation is not allowed due to the stream being closed.
     */
    class StreamClosedException extends StreamException { }
    
    /**
     * Is thrown when an attempt is made to read from a stream while another read is pending.
     */
    class PendingReadException extends StreamException { }
}

namespace Concurrent\Network
{
    use Concurrent\Stream\DuplexStream;
    use Concurrent\Stream\ReadableStream;
    use Concurrent\Stream\StreamException;
    use Concurrent\Stream\WritableStream;
    
    /**
     * Base contract for all sockets.
     */
    interface Socket
    {
        /**
         * Close the underlying socket.
         * 
         * @param \Throwable $e Reason for close, will be set as previous error.
         */
        public function close(?\Throwable $e = null): void;

        /**
         * Get the local address of the socket.
         */
        public function getAddress(): string;

        /**
         * Get the local network port, or NULL when no port is being used.
         */
        public function getPort(): ?int;

        /**
         * Change the value of a socket option, options are declared as class constants.
         * 
         * @param int $option Option to be changed.
         * @param mixed $value New value to be set.
         * @return bool Will return false when the option is not supported.
         */
        public function setOption(int $option, $value): bool;
    }

    /**
     * Contract for a reliable socket-based stream.
     */
    interface SocketStream extends Socket, DuplexStream
    {
        /**
         * Get the address of the remote peer.
         */
        public function getRemoteAddress(): string;

        /**
         * Get the network port used by the remote peer (or NULL if not network port is being used).
         */
        public function getRemotePort(): ?int;

        /**
         * Place the given data in the socket's send queue.
         * 
         * Implementations may try an immediate write before placeing data in the send queue.
         * 
         * @param string $data Data to be sent.
         * @return int Number of bytes in the socket's send queue.
         */
        public function writeAsync(string $data): int;
    }

    /**
     * Contract for a server that accepts reliable socket streams.
     */
    interface Server extends Socket
    {
        /**
         * Accept the next inbound socket connection.
         */
        public function accept(): SocketStream;
    }
    
    /**
     * TCP socket connection.
     */
    final class TcpSocket implements SocketStream
    {
        /**
         * Disables Nagle's Algorithm when set.
         */
        public const NODELAY = 100;
        
        /**
         * Sets the TCP keep-alive timeout in seconds, 0 to disable keep-alive.
         */
        public const KEEPALIVE = 101;
        
        /**
         * Sockets are created using connect() or TcpServer::accept().
         */
        private function __construct() { }
        
        /**
         * Connect to the given peer (will automatically perform a DNS lookup for host names).
         */
        public static function connect(string $host, int $port, ?TlsClientEncryption $tls = null): TcpSocket { }
        
        /**
         * Returns a pair of connected TCP sockets.
         */
        public static function pair(): array { }
        
        /**
         * {@inheritdoc}
         */
        public function close(?\Throwable $e = null): void { }
        
        /**
         * {@inheritdoc}
         */
        public function getAddress(): string { }
        
        /**
         * {@inheritdoc}
         */
        public function getPort(): ?int { }
        
        /**
         * {@inheritdoc}
         */
        public function setOption(int $option, $value): bool { }
        
        /**
         * {@inheritdoc}
         */
        public function getRemoteAddress(): string { }
        
        /**
         * {@inheritdoc}
         */
        public function getRemotePort(): ?int { }
        
        /**
         * Negotiate TLS connection encryption, any further data transfer is encrypted.
         */
        public function encrypt(): void { }
        
        /**
         * {@inheritdoc}
         */
        public function read(?int $length = null): ?string { }
        
        /**
         * {@inheritdoc}
         */
        public function readStream(): ReadableStream { }
        
        /**
         * {@inheritdoc}
         */
        public function write(string $data): void { }
        
        /**
         * {@inheritdoc}
         */
        public function writeAsync(string $data): int { }
        
        /**
         * {@inheritdoc}
         */
        public function writeStream(): WritableStream { }
    }
    
    /**
     * TCP socket server.
     */
    final class TcpServer implements Server
    {
        /**
         * Enable / disable simultaneous asynchronous accept requests that are queued by the operating system
         * when listening for new TCP connections.
         */
        public const SIMULTANEOUS_ACCEPTS = 150;
        
        /**
         * Servers are created using listen().
         */
        private function __construct() { }
        
        /**
         * Create a TCP server listening on the given interface and port.
         */
        public static function listen(string $host, int $port, ?TlsServerEncryption $tls = null): TcpServer { }
        
        /**
         * {@inheritdoc}
         */
        public function close(?\Throwable $e = null): void { }
        
        /**
         * {@inheritdoc}
         */
        public function getAddress(): string { }
        
        /**
         * {@inheritdoc}
         */
        public function getPort(): ?int { }
        
        /**
         * {@inheritdoc}
         */
        public function setOption(int $option, $value): bool { }
        
        /**
         * {@inheritdoc}
         */
        public function accept(): SocketStream { }
    }
    
    /**
     * Socket client encryption settings.
     */
    final class TlsClientEncryption
    {
        /**
         * Allow connecting to hosts that have a self-signed X509 certificate.
         */
        public function withAllowSelfSigned(bool $allow): TlsClientEncryption { }
        
        /**
         * Restrict the maximum certificate validation chain to the given length.
         */
        public function withVerifyDepth(int $depth): TlsClientEncryption { }
        
        /**
         * Set peer name to connect to.
         */
        public function withPeerName(string $name): TlsClientEncryption { }
    }
    
    /**
     * Socket server encryption settings.
     */
    final class TlsServerEncryption
    {
        /**
         * Configure the default X509 certificate to be used by the server.
         * 
         * @param string $cert Path to the certificate file.
         * @param string $key Path to the secret key file.
         * @param string $passphrase Passphrase being used to access the secret key.
         */
        public function withDefaultCertificate(string $cert, string $key, ?string $passphrase = null): TlsServerEncryption { }
        
        /**
         * Configure a host-based X509 certificate to be used by the server.
         * 
         * @param string $host Hostname.
         * @param string $cert Path to the certificate file.
         * @param string $key Path to the secret key file.
         * @param string $passphrase Passphrase being used to access the secret key.
         */
        public function withCertificate(string $host, string $cert, string $key, ?string $passphrase = null): TlsServerEncryption { }
    }
    
    /**
     * UDP socket API.
     */
    final class UdpSocket implements Socket
    {
        /**
         * Sets the maximum number of packet forwarding operations performed by routers.
         */
        public const TTL = 200;

        /**
         * Set to true to have multicast packets loop back to local sockets.
         */
        public const MULTICAST_LOOP = 250;

        /**
         * Sets the maximum number of packet forwarding operations performed by routers for multicast packets.
         */
        public const MULTICAST_TTL = 251;
        
        /**
         * Bind a UDP socket to the given local peer.
         * 
         * @param string $address Local network interface address (IP) to be used.
         * @param int $port Local port to be used.
         */
        public static function bind(string $address, int $port): UdpSocket { }
        
        /**
         * Bind a UDP socket and join the given UDP multicast group.
         * 
         * @param string $group Address (IP) of the UDP multicast group.
         * @param int $port Port being used by the UDP multicast group.
         */
        public static function multicast(string $group, int $port): UdpSocket { }
        
        /**
         * {@inheritdoc}
         */
        public function close(?\Throwable $e = null): void { }
        
        /**
         * {@inheritdoc}
         */
        public function getAddress(): string { }
        
        /**
         * {@inheritdoc}
         */
        public function getPort(): ?int { }
        
        /**
         * {@inheritdoc}
         */
        public function setOption(int $option, $value): bool { }
        
        /**
         * Receive the next UDP datagram from the socket.
         */
        public function receive(): UdpDatagram { }

        /**
         * Transmit the given UDP datagram over the network.
         * 
         * @param UdpDatagram $datagram UDP datagram with payload and remote peer address.
         */
        public function send(UdpDatagram $datagram): void { }
        
        /**
         * Enque the given UDP datagram to be sent over the network.
         * 
         * The datagram will only be enqueued if it cannot be sent immediately.
         * 
         * @param UdpDatagram $datagram UDP datagram with payload and remote peer address.
         * @return int Number of bytes in the socket's send queue.
         */
        public function sendAsync(UdpDatagram $datagram): int { }
    }
    
    /**
     * Wrapper for a UDP datagram.
     */
    final class UdpDatagram
    {
        /**
         * Transmitted data payload.
         * 
         * @var string
         */
        public $data;
        
        /**
         * IP address of the remote peer.
         * 
         * @var string
         */
        public $address;
        
        /**
         * Port being used by the remote peer.
         * 
         * @var int
         */
        public $port;
        
        /**
         * Create a new UDP datagram.
         * 
         * @param string $data Payload to be transmitted.
         * @param string $address IP address of the remote peer.
         * @param int $port Port being used by the remote peer.
         */
        public function __construct(string $data, string $address, int $port) { }
        
        /**
         * Create a UDP datagram with the same remote peer.
         * 
         * @param string $data Data to be transmitted.
         */
        public function withData(string $data): UdpDatagram { }

        /**
         * Create a datagram with the same transmitted data.
         * 
         * @param string $address IP address of the remote peer.
         * @param int $port Port being used by the remote peer.
         */
        public function withPeer(string $address, int $port): UdpDatagram { }
    }
    
    /**
     * Is thrown when a network-related error is encountered.
     */
    class SocketException extends StreamException { }
}

namespace Concurrent\Process
{
    use Concurrent\Stream\ReadableStream;
    use Concurrent\Stream\WritableStream;
                                
    /**
     * Provides a unified way to spawn a process.
     */
    final class ProcessBuilder
    {
        /**
         * File descriptor of STDIN.
         */
        public const STDIN = 0;

        /**
         * File descriptor of STDOUT.
         */
        public const STDOUT = 1;

        /**
         * File descriptor of STDERR.
         */
        public const STDERR = 2;

        /**
         * Redirect a pipe to /dev/null or NUL.
         */
        public const STDIO_IGNORE = 16;

        /**
         * Have a pipe inherit a handle from the parent process.
         */
        public const STDIO_INHERIT = 17;

        /**
         * Provide a pipe as readable / writable stream.
         */
        public const STDIO_PIPE = 18;

        /**
         * Create a new process configuration.
         * 
         * @param string $command Name of the command to be executed.
         * @param string ...$args Additional arguments / flags to be passed to the command.
         */
        public function __construct(string $command, string ...$args) { }
        
        /**
         * Set the work directory of the spawned process.
         */
        public function setDirectory(string $dir): void { }

        /**
         * Set environment variables to be passed to the spawned process.
         * 
         * @param array $env Keys are var names, values are (string) values.
         */
        public function setEnv(array $env): void { }

        /**
         * Toggle inheritance of parent environment variables.
         */
        public function inheritEnv(bool $inherit): void { }

        /**
         * Configure the STDIN pipe of the spawned process.
         * 
         * @param int $mode One of the ProcessBuilder::MODE_* constants.
         * @param int $fd One of STDIN, STDOUT, STDERR, only if MODE_INHERIT is set.
         */
        public function configureStdin(int $mode, ?int $fd = null): void { }

        /**
         * Configure the STDOUT pipe of the spawned process.
         *
         * @param int $mode One of the ProcessBuilder::MODE_* constants.
         * @param int $fd One of STDIN, STDOUT, STDERR, only if MODE_INHERIT is set.
         */
        public function configureStdout(int $mode, ?int $fd = null): void { }

        /**
         * Configure the STDERR pipe of the spawned process.
         *
         * @param int $mode One of the ProcessBuilder::MODE_* constants.
         * @param int $fd One of STDIN, STDOUT, STDERR, only if MODE_INHERIT is set.
         */
        public function configureStderr(int $mode, ?int $fd = null): void { }

        /**
         * Create the process and await termination.
         * 
         * @param string ...$args Additional arguments to be passed to the process.
         * @return int Exit code returned by the process.
         */
        public function execute(string ...$args): int { }

        /**
         * Spawn the process and return a process object.
         * 
         * @param string ...$args Additional arguments to be passed to the process.
         */
        public function start(string ...$args): Process { }
    }
    
    /**
     * Provides access to a started process.
     */
    final class Process
    {
        /**
         * Proccesses are created using ProcessBuilder::start().
         */
        private function __construct() { }
        
        /**
         * Check if the process has terminated yet.
         */
        public function isRunning(): bool { }

        /**
         * Get the identifier of the spawned process.
         */
        public function getPid(): int { }

        /**
         * Get the STDIN pipe stream.
         */
        public function getStdin(): WritableStream { }

        /**
         * Get the STDOUT pipe stream.
         */
        public function getStdout(): ReadableStream { }

        /**
         * Get the STDERR pipe stream.
         */
        public function getStderr(): ReadableStream { }

        /**
         * Send the given signal to the process.
         * 
         * @param int $signum Signal to be sent (use SignalWatcher constants to avoid magic numbers).
         */
        public function signal(int $signum): void { }

        /**
         * Await termination of the process.
         * 
         * @return int Exit code returned by the process.
         */
        public function awaitExit(): int { }
    }
}
