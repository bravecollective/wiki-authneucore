<?php

use Aura\Session\Session;
use Brave\CoreConnector\Bootstrap;
use Eve\Sso\AuthenticationProvider;
use Psr\Container\ContainerExceptionInterface;

require 'vendor/autoload.php';
const ROOT_DIR = __DIR__;

$bootstrap = new Bootstrap();
/** @var AuthenticationProvider $authenticationProvider */
try {
    $authenticationProvider = $bootstrap->getContainer()->get(AuthenticationProvider::class);
} catch (ContainerExceptionInterface $e) {
    error_log($e->getMessage());
    die('Error 500.');
}

$cb = $_GET['cb'] ?? '';

/** @var Session $session */
try {
    $session = $bootstrap->getContainer()->get(Session::class);
} catch (ContainerExceptionInterface $e) {
    error_log($e->getMessage());
    die('Error 500.');
}
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
