<?php

/*
 * This file is part of fof/extend.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Extend\Events;

use Flarum\User\User;

class LinkingToProvider
{
    /**
     * The OAuth provider name. This is used in the `login_providers` table as the `provider` column.
     *
     * @var string
     */
    public $providerName;

    /**
     * The providers unique identifier as given by the provider. This is used in the `login_providers` table as the `identifier` column.
     *
     * @var string
     */
    public $identifier;

    /**
     * The user object that is attempting to link their account.
     *
     * @var User
     */
    public $actor;

    public function __construct(string $providerName, string $identifier, User $actor)
    {
        $this->providerName = $providerName;
        $this->identifier = $identifier;
        $this->actor = $actor;
    }
}
