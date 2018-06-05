<?php
require 'vendor/autoload.php';

$bootstrap = new \Brave\CoreConnector\Bootstrap();
/** @var \Brave\Sso\Basics\AuthenticationProvider $authenticationProvider */
$authenticationProvider = $bootstrap->getContainer()->get(\Brave\Sso\Basics\AuthenticationProvider::class);

$cb = isset($_GET['cb']) ? $_GET['cb'] : '';

/** @var \Aura\Session\Session $session */
$session = $bootstrap->getContainer()->get(\Aura\Session\Session::class);
$state = $authenticationProvider->generateState('wiki');
$session->getSegment('Bravecollective_Neucore')->set('sso_state', $state);
$session->getSegment('Bravecollective_Neucore')->set('cb', $cb);

$loginUrl = $authenticationProvider->buildLoginUrl($state);
header('Location: ' . $loginUrl);
exit();
