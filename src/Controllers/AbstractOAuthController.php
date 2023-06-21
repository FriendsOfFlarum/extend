<?php

/*
 * This file is part of fof/extend.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Extend\Controllers;

use Flarum\Forum\Auth\Registration;
use Flarum\Forum\Auth\ResponseFactory;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\LoginProvider;
use Flarum\User\User;
use FoF\Extend\Events\OAuthLoginSuccessful;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Session\Store;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class AbstractOAuthController implements RequestHandlerInterface
{
    /**
     * @var ResponseFactory
     */
    protected $response;

    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @var UrlGenerator
     */
    protected $url;

    /**
     * @var Dispatcher
     */
    protected $events;

    public function __construct(ResponseFactory $response, SettingsRepositoryInterface $settings, UrlGenerator $url, Dispatcher $events)
    {
        $this->response = $response;
        $this->settings = $settings;
        $this->url = $url;
        $this->events = $events;
    }

    /**
     * @throws \Exception
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $redirectUri = $this->url->to('forum')->route($this->getRouteName());
        $provider = $this->getProvider($redirectUri);

        /** @var Store $session */
        $session = $request->getAttribute('session');
        $queryParams = $request->getQueryParams();
        $code = Arr::get($queryParams, 'code');
        $state = Arr::get($queryParams, 'state');

        if ($requestLinkTo = Arr::pull($queryParams, 'linkTo')) {
            $session->put('linkTo', $requestLinkTo);
        }

        if (!$code) {
            $authUrl = $provider->getAuthorizationUrl($this->getAuthorizationUrlOptions());
            $session->put('oauth2state', $provider->getState());

            return new RedirectResponse($authUrl.'&display=popup');
        } elseif (!$state || $state !== $session->get('oauth2state')) {
            $session->remove('oauth2state');

            throw new \Exception('Invalid state');
        }

        $token = $provider->getAccessToken('authorization_code', compact('code'));
        $user = $provider->getResourceOwner($token);

        $actor = RequestUtil::getActor($request);

        // Don't register a new user, just link to the existing account, else continue with registration.
        if ($session->has('linkTo') && $actor->exists) {
            $actor->assertRegistered();
            $sessionLink = (int) $session->remove('linkTo');

            if ($actor->id !== $sessionLink || $sessionLink === 0) {
                throw new ValidationException(['linkAccount' => 'User data mismatch']);
            }

            $response = $this->link($actor, $user);

            $this->dispatchSuccessEvent($token, $user, $actor);

            return $response;
        }

        $response = $this->response->make(
            $this->getProviderName(),
            $this->getIdentifier($user),
            function (Registration $registration) use ($user, $token) {
                $this->setSuggestions($registration, $user, $token);
            }
        );

        $this->dispatchSuccessEvent($token, $user, $actor);

        return $response;
    }

    private function dispatchSuccessEvent(AccessTokenInterface $token, ResourceOwnerInterface $user, ?User $actor): void
    {
        $this->events->dispatch(new OAuthLoginSuccessful($token, $user, $this->getProviderName(), $this->getIdentifier($user), $actor));
    }

    /**
     * Link the currently authenticated user to the OAuth account.
     *
     * @param ResourceOwnerInterface $resourceOwner
     */
    protected function link(User $user, $resourceOwner): HtmlResponse
    {
        if (LoginProvider::where('identifier', $this->getIdentifier($resourceOwner))->where('provider', $this->getProviderName())->exists()) {
            throw new ValidationException(['linkAccount' => 'Account already linked to another user']);
        }

        $user->loginProviders()->firstOrCreate([
            'provider' => $this->getProviderName(),
            'identifier' => $this->getIdentifier($resourceOwner),
        ])->touch();

        $content = '<script>window.close(); window.opener.app.linkingComplete();</script>';

        return new HtmlResponse($content);
    }

    /**
     * Get OAuth route name, used for redirect url
     * Example: 'auth.github'.
     */
    abstract protected function getRouteName(): string;

    /**
     * Get League OAuth 2.0 provider.
     */
    abstract protected function getProvider(string $redirectUri): AbstractProvider;

    /**
     * Get League OAuth 2.0 provider name.
     */
    abstract protected function getProviderName(): string;

    /**
     * Get authorization URL options.
     */
    abstract protected function getAuthorizationUrlOptions(): array;

    /**
     * Get user identifier.
     *
     * @param ResourceOwnerInterface $user
     */
    abstract protected function getIdentifier($user): string;

    /**
     * Set form suggestions.
     *
     * @param ResourceOwnerInterface $user
     *
     * @return void
     */
    abstract protected function setSuggestions(Registration $registration, $user, string $token);
}
