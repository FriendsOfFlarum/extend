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
use FoF\Extend\Events\LinkingToProvider;
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
     * Session key for OAuth2 state.
     */
    const SESSION_OAUTH2STATE = 'oauth2state';

    /**
     * Session key for OAuth2 provider.
     */
    const SESSION_OAUTH2PROVIDER = 'oauth2provider';

    /**
     * Session key for linkTo.
     */
    const SESSION_LINKTO = 'linkTo';

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

    protected static $afterOAuthSuccessCallbacks = [];

    public function __construct(
        ResponseFactory $response,
        SettingsRepositoryInterface $settings,
        UrlGenerator $url,
        Dispatcher $events
    ) {
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
        $provider = $this->identifyProvider();

        $session = $this->initializeSession($request, $provider);

        if ((bool) $session->get('fastTrack') === true && $session->has('oauth_data')) {
            $result = $this->fastTrack($session, $request);
            if ($result !== null) {
                return $result;
            }
        }

        if (!$this->hasAuthorizationCode($request)) {
            return $this->redirectToAuthorizationUrl($provider, $session);
        }

        $this->validateState($session, $request);

        $token = $this->obtainAccessToken($provider, Arr::get($request->getQueryParams(), 'code'));
        $userResource = $provider->getResourceOwner($token);

        return $this->handleOAuthResponse($request, $token, $userResource, $session);
    }

    /**
     * Fast Track OAuth Flow.
     *
     * The `fastTrack` method provides a mechanism to expedite the OAuth authentication process
     * under certain conditions. Specifically, when a session indicates a `fastTrack` state and
     * contains the necessary `oauth_data` (i.e., both `token` and `resourceOwner`), this method
     * can be utilized to bypass the standard flow and directly handle the OAuth response.
     *
     * This can be particularly useful in scenarios where the initial OAuth parameters, provided
     * by the authentication provider, have expired due to additional steps in the flow (e.g.,
     * two-factor authentication). Instead of going through the entire OAuth flow again, the
     * `fastTrack` mechanism uses the saved session data to resume and complete the process.
     *
     * It's essential to ensure the integrity and validity of the saved session data before
     * using this method. The method will return the response of the OAuth flow if the conditions
     * are met, or null otherwise.
     *
     * @param Store                  $session The current session instance containing potential OAuth data.
     * @param ServerRequestInterface $request The current server request.
     *
     * @return ResponseInterface|null The response of the OAuth flow if fast-tracked, or null.
     */
    protected function fastTrack(Store $session, ServerRequestInterface $request): ?ResponseInterface
    {
        $token = Arr::get($session->get('oauth_data'), 'token');
        $resourceOwner = Arr::get($session->get('oauth_data'), 'resourceOwner');

        if ($token instanceof AccessTokenInterface && $resourceOwner instanceof ResourceOwnerInterface) {
            return $this->handleOAuthResponse($request, $token, $resourceOwner, $session);
        }
    }

    protected function identifyProvider(): AbstractProvider
    {
        $redirectUri = $this->url->to('forum')->route($this->getRouteName());

        return $this->getProvider($redirectUri);
    }

    protected function initializeSession(ServerRequestInterface $request, AbstractProvider $provider): Store
    {
        $session = $request->getAttribute('session');
        $session->put(self::SESSION_OAUTH2PROVIDER, $this->getProviderName());

        if (method_exists($provider, 'setSession')) {
            $provider->setSession($session);
        }

        if ($requestLinkTo = Arr::get($request->getQueryParams(), 'linkTo')) {
            $session->put(self::SESSION_LINKTO, $requestLinkTo);
        }

        return $session;
    }

    /**
     * Determine if the request has an authorization code.
     *
     * @param ServerRequestInterface $request
     *
     * @return bool
     */
    protected function hasAuthorizationCode(ServerRequestInterface $request): bool
    {
        return Arr::has($request->getQueryParams(), 'code');
    }

    /**
     * Set the redirect response to the OAuth provider's authorization URL, and store the OAuth2 state.
     *
     * @param AbstractProvider $provider
     * @param Store            $session
     *
     * @return RedirectResponse
     */
    protected function redirectToAuthorizationUrl(AbstractProvider $provider, Store $session): RedirectResponse
    {
        $authUrl = $provider->getAuthorizationUrl($this->getAuthorizationUrlOptions());
        $session->put(self::SESSION_OAUTH2STATE, $provider->getState());

        return new RedirectResponse($authUrl.'&display='.$this->getDisplayType());
    }

    /**
     * Validate the OAuth2 state.
     *
     * @param Store                  $session
     * @param ServerRequestInterface $request
     *
     * @return void
     */
    protected function validateState(Store $session, ServerRequestInterface $request): void
    {
        $state = Arr::get($request->getQueryParams(), 'state');

        if (!$state || $state !== $session->get(self::SESSION_OAUTH2STATE)) {
            $this->handleOAuthError($session);
        }
    }

    /**
     * Remove the OAuth2 state and throw an exception.
     *
     * @param Store $session
     *
     * @return void
     */
    protected function handleOAuthError(Store $session): void
    {
        $session->remove(self::SESSION_OAUTH2STATE);
        $session->remove(self::SESSION_OAUTH2PROVIDER);

        throw new \Exception('Invalid state');
    }

    /**
     * Request an access token from the OAuth provider.
     *
     * @param AbstractProvider $provider
     * @param string           $code
     *
     * @return AccessTokenInterface
     */
    protected function obtainAccessToken(AbstractProvider $provider, string $code): AccessTokenInterface
    {
        return $provider->getAccessToken('authorization_code', compact('code'));
    }

    /**
     * Dispatch an event when OAuth login is successful.
     *
     * @param AccessTokenInterface   $token The access token.
     * @param ResourceOwnerInterface $user  The authenticated user's resource owner instance.
     * @param User|null              $actor The current authenticated actor.
     */
    protected function dispatchSuccessEvent(AccessTokenInterface $token, ResourceOwnerInterface $resourceOwner, ?User $actor): void
    {
        $this->events->dispatch(
            new OAuthLoginSuccessful(
                $token,
                $resourceOwner,
                $this->getProviderName(),
                $this->getIdentifier($resourceOwner),
                $actor
            )
        );
    }

    /**
     * Link the currently authenticated user to the OAuth account.
     *
     * @param ResourceOwnerInterface $resourceOwner
     */
    protected function link(User $user, ResourceOwnerInterface $resourceOwner): HtmlResponse
    {
        /** @var LoginProvider|null */
        $provider = LoginProvider::where('identifier', $this->getIdentifier($resourceOwner))
            ->where('provider', $this->getProviderName())
            ->first();

        if ($provider && $provider->exists() && $provider->user_id !== $user->id) {
            throw new ValidationException(['linkAccount' => 'Account already linked to another user']);
        }

        $this->events->dispatch(
            new LinkingToProvider(
                $this->getProviderName(),
                $this->getIdentifier($resourceOwner),
                $user
            )
        );

        $user->loginProviders()->firstOrCreate([
            'provider'   => $this->getProviderName(),
            'identifier' => $this->getIdentifier($resourceOwner),
        ])->touch();

        $content = '<script>window.close(); window.opener.app.linkingComplete();</script>';

        return new HtmlResponse($content);
    }

    protected function handleOAuthResponse(ServerRequestInterface $request, AccessTokenInterface $token, ResourceOwnerInterface $resourceOwner, Store $session): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        // Don't register a new user, just link to the existing account, else continue with registration.
        if ($session->has(self::SESSION_LINKTO) && $actor->exists) {
            $actor->assertRegistered();
            $sessionLink = (int) $session->remove(self::SESSION_LINKTO);

            if ($actor->id !== $sessionLink || $sessionLink === 0) {
                throw new ValidationException(['linkAccount' => 'User data mismatch']);
            }

            $response = $this->link($actor, $resourceOwner);
        } else {
            $response = $this->response->make(
                $this->getProviderName(),
                $this->getIdentifier($resourceOwner),
                function (Registration $registration) use ($resourceOwner, $token) {
                    $this->setSuggestions($registration, $resourceOwner, $token);
                }
            );
        }

        // Execute registered callbacks
        foreach (static::$afterOAuthSuccessCallbacks as $callback) {
            $result = $callback($request, $token, $resourceOwner, $this->getProviderName());

            if ($result !== null) {
                return $result;
            }
        }

        $this->dispatchSuccessEvent($token, $resourceOwner, $actor);

        return $response;
    }

    public static function setAfterOAuthSuccessCallbacks(array $callbacks)
    {
        static::$afterOAuthSuccessCallbacks = array_merge(static::$afterOAuthSuccessCallbacks, $callbacks);
    }

    /**
     * Get the display type for the OAuth process.
     *
     * @return string Returns the type of display, e.g. 'popup'.
     */
    protected function getDisplayType(): string
    {
        return 'popup';
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
