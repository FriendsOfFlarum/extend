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
    /**
     * The access token provided by the service.
     *
     * @var AccessTokenInterface
     */
    public $token;

    /**
     * THe complete ResourceOwner object.
     *
     * @var ResourceOwnerInterface
     */
    public $userResource;

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
     * For a "normal" login, this value will always be `Guest`, as when this event is dispatched we have not yet completed the complete login flow.
     *
     * If a logged in user is attemping to link their existing Flarum account with a OAuth provider, this will contain the current user object.
     *
     * @var ?User
     */
    public $actor;

    public function __construct(AccessTokenInterface $token, ResourceOwnerInterface $userResource, string $providerName, string $identifier, ?User $actor)
    {
        $this->token = $token;
        $this->userResource = $userResource;
        $this->providerName = $providerName;
        $this->identifier = $identifier;
        $this->actor = $actor;
    }
}
