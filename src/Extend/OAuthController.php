<?php

/*
 * This file is part of fof/extend.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Extend\Extend;

use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Flarum\Foundation\ContainerUtil;
use FoF\Extend\Controllers\AbstractOAuthController;
use Illuminate\Contracts\Container\Container;

class OAuthController implements ExtenderInterface
{
    protected $afterOAuthSuccessCallbacks = [];

    /**
     * Register a callback to be executed after a successful OAuth login, but before the user is logged in to Flarum.
     *
     * @param callable|string $callback
     *
     * The callback can be a closure or an invokable class and should accept:
     * - $request: An instance of \Psr\Http\Message\ServerRequestInterface.
     * - $token: An instance of \League\OAuth2\Client\Token\AccessTokenInterface, representing the access token.
     * - $resourceOwner: An instance of \League\OAuth2\Client\Provider\ResourceOwnerInterface, representing the authenticated user's resource.
     * - $identification: A string identifying `fof/oauth` provider, e.g. `github.
     *
     * It should return either `void` if no further action is required from the callback, or `Psr\Http\Message\ResponseInterface`.
     *
     * @return $this
     */
    public function afterOAuthSuccess($callback)
    {
        $this->afterOAuthSuccessCallbacks[] = $callback;

        return $this;
    }

    public function extend(Container $container, Extension $extension = null)
    {
        foreach ($this->afterOAuthSuccessCallbacks as $index => $callback) {
            $this->afterOAuthSuccessCallbacks[$index] = ContainerUtil::wrapCallback($callback, $container);
        }

        AbstractOAuthController::setAfterOAuthSuccessCallbacks($this->afterOAuthSuccessCallbacks);
    }
}
