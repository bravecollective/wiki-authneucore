<?php

use Aura\Session\Session;
use Brave\CoreConnector\Bootstrap;
use Brave\CoreConnector\Helper;
use Brave\NeucoreApi\Api\ApplicationGroupsApi;
use Eve\Sso\AuthenticationProvider;
use Psr\Container\ContainerExceptionInterface;

require 'vendor/autoload.php';
const ROOT_DIR = __DIR__;

$bootstrap = new Bootstrap();
try {
    $settings = $bootstrap->getContainer()->get('settings');
} catch (ContainerExceptionInterface $e) {
    error_log($e->getMessage());
    die('Error 500.');
}

/** @var Session $session */
try {
    $session = $bootstrap->getContainer()->get(Session::class);
} catch (ContainerExceptionInterface $e) {
    error_log($e->getMessage());
    die('Error 500.');
}
$sessionState = $session->getSegment('Bravecollective_Neucore')->get('sso_state');

$helper = new Helper($settings['ESI_DOMAIN']);

if (!isset($_GET['code']) || !isset($_GET['state']) || empty($sessionState)) {
    echo 'Invalid SSO state, <a href="/start?do=login">please try again</a>.';
    exit;
}

$code = $_GET['code'];
$state = $_GET['state'];

/** @var AuthenticationProvider $authenticationProvider */
try {
    $authenticationProvider = $bootstrap->getContainer()->get(AuthenticationProvider::class);
} catch (ContainerExceptionInterface $e) {
    error_log($e->getMessage());
    die('Error 500.');
}
try {
    $eveAuthentication = $authenticationProvider->validateAuthenticationV2($state, $sessionState, $code);
} catch(UnexpectedValueException $uve) {
    echo $uve->getMessage(), '<br>',
        '<a href="/start?do=login">Please try again</a>.';
    exit;
}

$session->getSegment('Bravecollective_Neucore')->set('eveAuth', $eveAuthentication);

/** @var ApplicationGroupsApi $groupApi */
try {
    $groupApi = $bootstrap->getContainer()->get(ApplicationGroupsApi::class);
} catch (ContainerExceptionInterface $e) {
    error_log($e->getMessage());
    die('Error 500.');
}

// -----------------------------------------------

$token = (string)$eveAuthentication->getToken();
$charid = $eveAuthentication->getCharacterId();
$charname = $eveAuthentication->getCharacterName();
$username = preg_replace("/[^A-Za-z0-9]/", '_', strtolower($charname));
//$corpid = $result->corporation->id;
//$corpname = $result->corporation->name;
//$allianceid = $result->alliance->id;
//$alliancename = $result->alliance->name;
$corpid = 0;
$corpname = '';
$allianceid = 0;
$alliancename = '';

$tags = $helper->getCoreGroups($groupApi, $eveAuthentication);
if (count($tags) === 0) {
    echo '<strong>No groups found for this character or corporation.</strong><br><br>',
        'Please register at <a href="'.$settings['CORE_URL'].'">BRAVE Core</a>. ',
        'If the member group is listed on the right, try again here.<br><br>' .
        '<a href="/start?do=login">Back to Login</a>';
    exit;
}

// -----------------------------------------------

/** @var $db PDO */
try {
    $db = $bootstrap->getContainer()->get(PDO::class);
} catch (ContainerExceptionInterface $e) {
    error_log($e->getMessage());
    die('Error 500.');
}

// -----------------------------------------------

$groups = array('user');
$groups = $helper->addGroup($db, $groups, 'charid_' . $charid);
//$groups = addGroup($db, $groups, 'corpid_' . $corpid);
//$groups = addGroup($db, $groups, 'allianceid_' . $allianceid);
foreach ($tags as $tkey => $tvalue) {
    $groups = $helper->addGroup($db, $groups, 'tag_' . $tvalue);
}
$groups = array_unique($groups);

// -----------------------------------------------

$banned = $helper->addBan($db, false, 'charid_' . $charid);
//$banned = addBan($db, $banned, 'corpid_' . $corpid);
//$banned = addBan($db, $banned, 'allianceid_' . $allianceid);
foreach ($tags as $tkey => $tvalue) {
    $banned = $helper->addBan($db, $banned, 'tag_' . $tvalue);
}

if ($banned) {
    $groups = array('user');
}

// -----------------------------------------------

$stm = $db->prepare('SELECT charid FROM user WHERE charid = :charid;');
$stm->bindValue(':charid', $charid);
if (!$stm->execute()) {
    error_log('user query failed');
    echo 'An error occurred, <a href="/start?do=login">please try again</a>.';
    exit;
}

if ($stm->fetch()) {
    $stm = $db->prepare(
        'UPDATE user SET 
            username = :username, groups = :groups, charname = :charname, corpid = :corpid, 
            corpname = :corpname, allianceid = :allianceid, alliancename = :alliancename, 
            authtoken = :authtoken, authlast = :now 
        WHERE charid = :charid;'
    );
} else {
    $stm = $db->prepare(
        'INSERT INTO user 
            (username, groups, charid, charname, corpid, corpname, allianceid, alliancename, 
             authtoken, authcreated, authlast) 
        VALUES (:username, :groups, :charid, :charname, :corpid, :corpname, :allianceid, :alliancename, 
                :authtoken, :now, :now);'
    );
}
$stm->bindValue(':username', $username);
$stm->bindValue(':groups', implode(',', $groups));
$stm->bindValue(':charid', $charid, PDO::PARAM_INT);
$stm->bindValue(':charname', $charname);
$stm->bindValue(':corpid', $corpid, PDO::PARAM_INT);
$stm->bindValue(':corpname', $corpname);
$stm->bindValue(':allianceid', $allianceid, PDO::PARAM_INT);
$stm->bindValue(':alliancename', $alliancename);
$stm->bindValue(':authtoken', $token);
$stm->bindValue(':now', time(), PDO::PARAM_INT);
if (!$stm->execute()) {
    error_log('user insert or update failed');
    echo 'An error occurred, <a href="/start?do=login">please try again</a>.';
    exit;
}

$stm = $db->prepare('DELETE from session where sessionid = :sessionid;');
$stm->bindValue(':sessionid', $session->getId());
if (!$stm->execute()) {
    error_log('session cleanup failed');
    echo 'An error occurred, <a href="/start?do=login">please try again</a>.';
    exit;
}

$stm = $db->prepare('INSERT INTO session (sessionid, charid, created) VALUES (:sessionid, :charid, :created)');
$stm->bindValue(':sessionid', $session->getId());
$stm->bindValue(':charid', $charid);
$stm->bindValue(':created', time());
if (!$stm->execute()) {
    error_log('session insert failed');
    echo 'An error occurred, <a href="/start?do=login">please try again</a>.';
    exit;
}

// -----------------------------------------------

$cb = $session->getSegment('Bravecollective_Neucore')->get('cb');

header("Location: " . '/' . $cb);
