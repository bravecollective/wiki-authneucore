<?php

use Aura\Session\Session;
use Brave\CoreConnector\Bootstrap;
use Eve\Sso\AuthenticationProvider;

require 'vendor/autoload.php';
const ROOT_DIR = __DIR__;

$bootstrap = new Bootstrap();
/** @var AuthenticationProvider $authenticationProvider */
$authenticationProvider = $bootstrap->getContainer()->get(AuthenticationProvider::class);

$cb = $_GET['cb'] ?? '';

/** @var Session $session */
$session = $bootstrap->getContainer()->get(Session::class);
try {
    $state = $authenticationProvider->generateState('wiki');
} catch (Exception $e) {
    error_log($e->getMessage());
    exit();
}
$session->getSegment('Bravecollective_Neucore')->set('sso_state', $state);
$session->getSegment('Bravecollective_Neucore')->set('cb', $cb);

$loginUrl = $authenticationProvider->buildLoginUrl($state);
header('Location: ' . $loginUrl);
exit();
