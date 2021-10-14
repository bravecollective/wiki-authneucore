<?php

use Aura\Session\Session;
use Aura\Session\SessionFactory;
use Brave\NeucoreApi\Api\ApplicationGroupsApi;
use Brave\NeucoreApi\Configuration;
use Eve\Sso\AuthenticationProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use Pimple\Container;

return [
    'settings' => require_once('config.php'),

    GenericProvider::class => function (Container $container) {
        $settings = $container['settings'];

        return new GenericProvider([
            'clientId' => $settings['SSO_CLIENT_ID'],
            'clientSecret' => $settings['SSO_CLIENT_SECRET'],
            'redirectUri' => $settings['SSO_REDIRECTURI'],
            'urlAuthorize' => $settings['SSO_URL_AUTHORIZE'],
            'urlAccessToken' => $settings['SSO_URL_ACCESSTOKEN'],
            'urlResourceOwnerDetails' => $settings['SSO_URL_RESOURCEOWNERDETAILS'],
        ]);
    },

    AuthenticationProvider::class => function (Container $container) {
        $settings = $container['settings'];

        return new AuthenticationProvider(
            $container[GenericProvider::class],
            explode(' ', $settings['SSO_SCOPES'])
        );
    },

    ApplicationGroupsApi::class => function (Container $container) {
        $settings = $container['settings'];
        $configuration = new Configuration();
        $token = base64_encode($settings['CORE_APP_ID'] . ':' . $settings['CORE_APP_TOKEN']);
        $configuration = $configuration
            ->setHost($settings['CORE_URL'].'/api')
            ->setAccessToken( $token);
        return new ApplicationGroupsApi(null, $configuration, null);
    },

    PDO::class => function (Container $container) {
        $settings = $container['settings'];
        return new PDO($settings['DB_URL']);
    },

    Session::class => function (){
        $session_factory = new SessionFactory;
        $session = $session_factory->newInstance($_COOKIE);
        $session->setName('authneucore');
        return $session;
    }
];
