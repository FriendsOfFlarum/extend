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
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;

class OAuthLoginSuccessful
{
    public function __construct(
        /**
         * @param $token
         *               The access token provided by the service.
         */
        public AccessTokenInterface $token,

        /**
         * @param $userResource
         *                      The complete ResourceOwner object.
         */
        public ResourceOwnerInterface $userResource,

        /**
         * @param $providerName
         *                      The OAuth provider name. This is used in the `login_providers` table as the `provider` column.
         */
        public string $providerName,

        /**
         * @param $identifier
         *                    For a "normal" login, this value will always be `Guest`, as when this event is dispatched we have not yet completed the complete login flow.
         *
         * If a logged in user is attemping to link their existing Flarum account with a OAuth provider, this will contain the current user object.
         */
        public string $identifier,

        /**
         * @param $actor
         *               For a "normal" login, this value will always be `Guest`, as when this event is dispatched we have not yet completed the complete login flow.
         *
         * If a logged-in user is attempting to link their existing Flarum account with a OAuth provider, this will contain the current user object.
         */
        public ?User $actor = null
    ) {
    }
}
