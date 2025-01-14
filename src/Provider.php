<?php

namespace Mpociot\Socialite\Slack;

use GuzzleHttp\ClientInterface;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider implements ProviderInterface
{
    /**
     * Unique Provider Identifier.
     */
    const IDENTIFIER = 'SLACK';

    public $user;

    /**
     * {@inheritdoc}
     */
    protected $scopes = [];

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(
            'https://slack.com/oauth/authorize',
            $state
        );
    }

    public function user()
    {
        if ($this->hasInvalidState()) {
            throw new InvalidStateException();
        }

        $response = $this->getAccessTokenResponse($this->getCode());
        $this->user = $response['user'];
        $this->credentialsResponseBody = $response;

        $token = $this->parseAccessToken($response);
        $user = $this->mapUserToObject($this->user);

        if ($user instanceof User) {
            $user->setAccessTokenResponseBody($this->credentialsResponseBody);
        }

        return $user->setToken($token)
                    ->setRefreshToken($this->parseRefreshToken($response))
                    ->setExpiresIn($this->parseExpiresIn($response));
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://slack.com/api/oauth.access';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get(
            'https://slack.com/api/users.identity?token='.$token
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id' => $user['id'],
            'nickname' => $user['name'],
            'name' => $user['name'],
            'email' => $user['email'],
            'avatar' => $user['image_192'],
        ]);
    }

    /**
     * Get the account ID of the current user.
     *
     * @param string $token
     *
     * @return string
     */
    protected function getUserId($token)
    {
        $response = $this->getHttpClient()->get(
            'https://slack.com/api/auth.test?token='.$token
        );

        $response = json_decode($response->getBody()->getContents(), true);

        return $response['user_id'];
    }

    /**
     * Get the access token for the given code.
     *
     * @param string $code
     *
     * @return string
     */
    public function getAccessToken($code)
    {
        $postKey = (version_compare(ClientInterface::VERSION, '6') === 1) ? 'form_params' : 'body';

        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            $postKey => $this->getTokenFields($code),
        ]);

        $this->credentialsResponseBody = json_decode($response->getBody(), true);

        return json_decode($response->getBody(), true)['access_token'];
    }
}
