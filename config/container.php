<?php

return [
    'settings' => require_once('config.php'),

    \League\OAuth2\Client\Provider\GenericProvider::class => function (\Pimple\Container $container)
    {
        $settings = $container['settings'];

        return new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId' => $settings['SSO_CLIENT_ID'],
            'clientSecret' => $settings['SSO_CLIENT_SECRET'],
            'redirectUri' => $settings['SSO_REDIRECTURI'],
            'urlAuthorize' => $settings['SSO_URL_AUTHORIZE'],
            'urlAccessToken' => $settings['SSO_URL_ACCESSTOKEN'],
            'urlResourceOwnerDetails' => $settings['SSO_URL_RESOURCEOWNERDETAILS'],
        ]);
    },

    \Brave\Sso\Basics\AuthenticationProvider::class => function (\Pimple\Container $container)
    {
        $settings = $container['settings'];

        return new \Brave\Sso\Basics\AuthenticationProvider(
            $container[\League\OAuth2\Client\Provider\GenericProvider::class],
            explode(' ', $settings['SSO_SCOPES'])
        );
    },

    \Brave\NeucoreApi\Api\ApplicationApi::class => function (\Pimple\Container $container)
    {
        $settings = $container['settings'];
        $configuration = new \Brave\NeucoreApi\Configuration();
        $token = base64_encode($settings['CORE_APP_ID'] . ':' . $settings['CORE_APP_TOKEN']);
        $configuration = $configuration->setHost($settings['CORE_URL'])->setApiKey('Authorization', $token)->setApiKeyPrefix('Authorization', 'Bearer');
        return new \Brave\NeucoreApi\Api\ApplicationApi(null, $configuration, null);
    },

    PDO::class => function (\Pimple\Container $container) {
        $settings = $container['settings'];
        return new \PDO($settings['DB_URL']);
    },

    \Aura\Session\Session::class => function (\Pimple\Container $container)
    {
        $session_factory = new \Aura\Session\SessionFactory;
        $session = $session_factory->newInstance($_COOKIE);
        $session->setName('authneucore');
        return $session;
    }
];
