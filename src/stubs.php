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
     * Async DNS name resolution (will be blocking if PHP SAPI is not cli or phpdbg for now).
     * 
     * @throws \Throwable When host name is invalid or no IP address could be resolved.
     */
    function gethostbyname(string $host): string { }

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
    use Concurrent\Stream\WritableStream;
    
    /**
     * TCP socket connection.
     */
    final class TcpSocket implements DuplexStream
    {
        /**
         * Sockets are created using connect() or TcpServer::accept().
         */
        private function __construct() { }
        
        /**
         * Connect to the given peer (will automatically perform a DNS lookup for host names).
         */
        public static function connect(string $host, int $port, ?ClientEncryption $encryption = null): TcpSocket { }
        
        /**
         * Returns a pair of connected TCP sockets.
         */
        public static function pair(): array { }
        
        /**
         * {@inheritdoc}
         */
        public function close(?\Throwable $e = null): void { }
        
        /**
         * Togle TCP nodelay mode.
         */
        public function nodelay(bool $enable): void { }
        
        /**
         * Get IP address and port of the local peer.
         */
        public function getLocalPeer(): array { }
        
        /**
         * Get IP address and port of the remote peer.
         */
        public function getRemotePeer(): array { }
        
        /**
         * Negotiate connection encryption, any further data transfer is encrypted.
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
        public function writeStream(): WritableStream { }
    }
    
    /**
     * TCP socket server.
     */
    final class TcpServer
    {
        /**
         * Servers are created using listen().
         */
        private function __construct() { }
        
        /**
         * Create a TCP server listening on the given interface and port.
         */
        public static function listen(string $host, int $port, ?ServerEncryption $encryption = null): TcpServer { }
        
        /**
         * Dispose of the server.
         * 
         * @param \Throwable $e Reason for close.
         */
        public function close(?\Throwable $e = null): void { }
        
        /**
         * Get the host as specified during server creation.
         */
        public function getHost(): string { }
        
        /**
         * Get the port as specified during server creation.
         */
        public function getPort(): int { }
        
        /**
         * Get IP address and port of the local server socket.
         */
        public function getPeer(): array { }
        
        /**
         * Accept the next incoming client connection.
         */
        public function accept(): TcpSocket { }
    }
    
    /**
     * Socket client encryption settings.
     */
    final class ClientEncryption
    {
        /**
         * Allow connecting to hosts that have a self-signed X509 certificate.
         */
        public function withAllowSelfSigned(bool $allow): self { }
        
        /**
         * Restrict the maximum certificate validation chain to the given length.
         */
        public function withVerifyDepth(int $depth): self { }
        
        /**
         * Set peer name to connect to.
         */
        public function withPeerName(string $name): self { }
    }
    
    /**
     * Socket server encryption settings.
     */
    final class ServerEncryption
    {
        /**
         * Configure the default X509 certificate to be used by the server.
         * 
         * @param string $cert Path to the certificate file.
         * @param string $key Path to the secret key file.
         * @param string $passphrase Passphrase being used to access the secret key.
         */
        public function withDefaultCertificate(string $cert, string $key, ?string $passphrase = null): self { }
    }
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
