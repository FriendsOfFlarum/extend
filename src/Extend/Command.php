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
