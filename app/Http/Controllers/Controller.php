<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Laravel 11+'s generated base Controller is bare (no traits, no
 * middleware() support) — extending Illuminate\Routing\Controller restores
 * middleware()/getMiddleware() (needed by authorizeResource(), which several
 * Core controllers rely on), plus the AuthorizesRequests trait for
 * authorize()/authorizeResource() themselves.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
}
