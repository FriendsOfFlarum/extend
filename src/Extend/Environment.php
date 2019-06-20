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
use Illuminate\Support\Arr;

class Environment implements ExtenderInterface
{
    /**
     * @var string
     */
    private $environment;

    public function __construct(string $environment = null)
    {
        $this->environment = $environment;
    }

    public function extend(Container $container, Extension $extension = null)
    {
        $container->extend('env', function (string $env) use ($container) {
            if (!$this->environment) {
                return $env;
            }

            if (is_callable($this->environment)) {
                return $this->environment($container);
            }

            return $this->environment;
        });
    }

    public function fromEnv(string $key = 'APP_ENV')
    {
        $this->environment = env($key) ?? $this->environment;

        return $this;
    }

    public function fromConfig(string $key = 'app.env')
    {
        // Use a callable so we don't resolve bindings too early.
        $this->environment = function (Container $container) use ($key) {
            $config = $container->make('flarum.config') ?? [];

            return Arr::get($config, $key);
        };

        return $this;
    }
}
