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
    public function __construct(
        /**
         * @param $providerName
         *                      The OAuth provider name. This is used in the `login_providers` table as the `provider` column.
         */
        public string $providerName,

        /**
         * @param $identifier
         *                    The provider's unique identifier as given by the provider. This is used in the `login_providers` table as the `identifier` column.
         */
        public string $identifier,

        /**
         * @param $actor
         *               The user object that is attempting to link their account.
         */
        public User $actor
    ) {
    }
}
