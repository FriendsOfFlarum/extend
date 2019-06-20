<?php

namespace FoF\Extend\Extend;

use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Flarum\Post\Event\CheckingForFlooding;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;

class DisableFloodGate implements ExtenderInterface
{
    /** @var array|int[] */
    protected $groups = [];
    /** @var array|int[] */
    protected $users = [];

    public function extend(Container $container, Extension $extension = null)
    {
        /** @var Dispatcher $events */
        $events = $container->make(Dispatcher::class);

        $events->listen(CheckingForFlooding::class, function (CheckingForFlooding $event) {
            if ($event->actor->isGuest()) {
                return null;
            }

            $whiteListed = in_array($event->actor->id, $this->users) || $event->actor->groups()
                ->whereIn('id', $this->groups)
                ->exists();

            return $whiteListed ? false : null;
        });
    }

    public function forGroup(int $groupId)
    {
        $this->groups[] = $groupId;

        return $this;
    }

    public function forUser(int $userId)
    {
        $this->users[] = $userId;

        return $this;
    }
}
