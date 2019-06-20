<?php

/*
 * This file is part of fof/extend.
 *
 * Copyright (c) 2019 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Extend\Extend;

use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Illuminate\Contracts\Container\Container;
use Zend\Stratigility\MiddlewarePipe;

class Middleware implements ExtenderInterface
{
    /**
     * @var string
     */
    private $frontend;
    /**
     * @var string
     */
    private $middleware = [];

    public function __construct(string $frontend = 'forum', array $middleware = [])
    {
        $this->frontend = $frontend;
        $this->middleware = $middleware;
    }

    public function extend(Container $container, Extension $extension = null)
    {
        $container->resolving("flarum.{$this->frontend}.middleware", function (MiddlewarePipe $pipe) use ($container) {
            foreach ($this->middleware as $middleware) {
                $pipe->pipe($container->make($middleware));
            }
        });
    }

    public function add(string $middleware)
    {
        $this->middleware[] = $middleware;

        return $this;
    }
}
