<?php

namespace Frontend\Palm;

/**
 * Middleware Interface for Frontend Routes
 */
interface MiddlewareInterface
{
    /**
     * Handle the request
     * 
     * @param callable $next Next middleware or route handler
     * @return mixed
     */
    public function handle(callable $next): mixed;
}

