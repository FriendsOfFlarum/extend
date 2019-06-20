<?php

namespace FoF\Extend\Extend;

use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Illuminate\Contracts\Container\Container;

class Provider implements ExtenderInterface
{
    private $providers = [];

    public function __construct(array $providers = [])
    {
        $this->providers = $providers;
    }

    public function extend(Container $container, Extension $extension = null)
    {
        foreach ($this->providers as $provider) {
            $container->register(new $provider($container));
        }
    }

    public function add(string $provider)
    {
        $this->providers[] = $provider;

        return $this;
    }
}
