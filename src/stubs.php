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

namespace Concurrent;

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
    
    /**
     * Lookup the background (= root) context.
     */
    public static function background(): Context { }
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
 * A deferred represents an async operation that may not have completed yet.
 * 
 * It exposes an awaitable to be consumed by other components and provides an API
 * to resolve or fail the awaitable at any time.
 */
final class Deferred
{
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
