<?php

namespace FoF\Extend\Controllers;

use Exception;
use Flarum\Forum\Auth\Registration;
use Flarum\Forum\Auth\ResponseFactory;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Zend\Diactoros\Response\RedirectResponse;

abstract class AbstractOAuthController implements RequestHandlerInterface {
  /**
   * @var ResponseFactory
   */
  protected $response;

  /**
   * @var SettingsRepositoryInterface
   */
  protected $settings;

  /**
   * @param ResponseFactory             $response
   * @param SettingsRepositoryInterface $settings
   */
  public function __construct(ResponseFactory $response, SettingsRepositoryInterface $settings)
  {
    $this->response = $response;
    $this->settings = $settings;
  }

  /**
   * @param ServerRequestInterface $request
   *
   * @throws Exception
   *
   * @return ResponseInterface
   */
  public function handle(ServerRequestInterface $request): ResponseInterface
  {
    $redirectUri = (string) $request->getAttribute('originalUri', $request->getUri())->withQuery('');
    $provider = $this->getProvider($redirectUri);
    
    $session = $request->getAttribute('session');
    $queryParams = $request->getQueryParams();
    $code = array_get($queryParams, 'code');
    $state = array_get($queryParams, 'state');

    if (!$code) {
      $authUrl = $provider->getAuthorizationUrl($this->getAuthorizationUrlOptions());
      $session->put('oauth2state', $provider->getState());

      return new RedirectResponse($authUrl . '&display=popup');
    } else if (!$state || $state !== $session->get('oauth2state')) {
      $session->remove('oauth2state');

      throw new Exception('Invalid state');
    }

    $token = $provider->getAccessToken('authorization_code', compact('code'));
    $user = $provider->getResourceOwner($token);

    return $this->response->make(
      $this->getProviderName(),
      $this->getIdentifier($user),
      function (Registration $registration) use ($user, $token) {
        $this->setSuggestions($registration, $user, $token);
      }
    );
  }

  /**
   * Get League OAuth 2.0 provider
   *
   * @param string $redirectUri
   * @return AbstractProvider
   */
  abstract protected function getProvider(string $redirectUri) : AbstractProvider;

  /**
   * Get League OAuth 2.0 provider name
   *
   * @return string
   */
  abstract protected function getProviderName() : string;

  /**
   * Get authorization URL options
   *
   * @return array
   */
  abstract protected function getAuthorizationUrlOptions() : array;

  /**
   * Get user identifier
   *
   * @param ResourceOwnerInterface $user
   * @return string
   */
  abstract protected function getIdentifier($user) : string;

  /**
   * Set form suggestions
   *
   * @param Registration $registration
   * @param ResourceOwnerInterface $user
   * @param string $token
   * @return void
   */
  abstract protected function setSuggestions(Registration $registration, $user, string $token);
}