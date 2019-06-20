<?php

namespace FoF\Extend\Extend;

use Flarum\Console\Event\Configuring;
use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Illuminate\Contracts\Container\Container;

class Command implements ExtenderInterface
{
    protected $commands = [];

    public function __construct(array $commands = [])
    {
        $this->commands = $commands;
    }

    public function extend(Container $container, Extension $extension = null)
    {
        $container->make('events')->listen(Configuring::class, function (Configuring $event) {
            foreach ($this->commands as $command) {
                $event->addCommand($command);
            }
        });
    }

    public function add(string $command)
    {
        $this->commands[] = $command;

        return $this;
    }
}
