<?php

use Aura\Session\Session;
use Brave\CoreConnector\Bootstrap;
use Brave\NeucoreApi\Api\ApplicationApi;
use Brave\NeucoreApi\ApiException;
use Brave\NeucoreApi\Model\Group;
use Brave\Sso\Basics\AuthenticationProvider;
use Brave\Sso\Basics\EveAuthentication;

require('vendor/autoload.php');
const ROOT_DIR = __DIR__;

$bootstrap = new Bootstrap();
/** @var AuthenticationProvider $authenticationProvider */
$authenticationProvider = $bootstrap->getContainer()->get(AuthenticationProvider::class);

/** @var Session $session */
$session = $bootstrap->getContainer()->get(Session::class);
$sessionState = $session->getSegment('Bravecollective_Neucore')->get('sso_state');

if (!isset($_GET['code']) || !isset($_GET['state'])) {
    echo 'Invalid SSO state, please try again.';
    exit;
}

$code = $_GET['code'];
$state = $_GET['state'];

$authenticationProvider = $bootstrap->getContainer()->get(AuthenticationProvider::class);
try {
    $eveAuthentication = $authenticationProvider->validateAuthentication($state, $sessionState, $code);
} catch(UnexpectedValueException $uve) {
    echo $uve->getMessage(), '<br>',
        '<a href="/">Please try again.</a>';
    exit;
}

$session->getSegment('Bravecollective_Neucore')->set('eveAuth', $eveAuthentication);

/** @var ApplicationApi $applicationApi */
$applicationApi = $bootstrap->getContainer()->get(ApplicationApi::class);

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

function getCoreGroups(
    ApplicationApi $applicationApi,
    EveAuthentication $eveAuthentication
): array {
    $charId = $eveAuthentication->getCharacterId();

    // try player account
    try {
        $coreGroups = $applicationApi->groupsV2($charId);
    } catch (ApiException $e) {
        // probably 404 Character not found
        error_log($e->getMessage());
        return [];
    }

    // try alliance groups
    if (count($coreGroups) === 0) {
        $esiResult = file_get_contents('https://esi.evetech.net/latest/characters/' . $charId);
        $charData = json_decode($esiResult);
        if ($charData instanceof stdClass) {
            try {
                $coreGroups = $applicationApi->allianceGroupsV2((int) $charData->alliance_id);
            } catch (ApiException $e) {
                // probably 404 Alliance not found
                error_log($e->getMessage());
                return [];
            }
        }
    }

    return array_map(function (Group $group) {
        return $group->getName();
    }, $coreGroups);
}

$tags = getCoreGroups($applicationApi, $eveAuthentication);
if (count($tags) === 0) {
    echo '<strong>No groups found for this character or alliance.</strong><br><br>',
        'Please register at <a href="'.$bootstrap->getContainer()->get('settings')['CORE_URL'].'">BRAVE Core</a>. ',
        'If groups are listed on the right, try again here.<br><br>' .
        'If your alliance is a member of the Legacy Coalition, you should have access, maybe ESI is down?<br><br>'.
        '<a href="/start?do=login">Back to Login</a>';
    exit;
}

// -----------------------------------------------

/** @var $db PDO */
$db = $bootstrap->getContainer()->get(PDO::class);

// -----------------------------------------------

function addGroup(PDO $db, $groups, $criteria): array {
    $stm = $db->prepare('SELECT grp FROM grp WHERE criteria = :criteria;');
    $stm->bindValue(':criteria', $criteria);
    if (!$stm->execute()) {
        error_log('group query failed');
        return [];
    }
    while ($res = $stm->fetch()){
        $groups[] = $res['grp'];
    }
    return $groups;
}

$groups = array('user');
$groups = addGroup($db, $groups, 'charid_' . $charid);
//$groups = addGroup($db, $groups, 'corpid_' . $corpid);
//$groups = addGroup($db, $groups, 'allianceid_' . $allianceid);
foreach ($tags as $tkey => $tvalue) {
    $groups = addGroup($db, $groups, 'tag_' . $tvalue);
}
$groups = array_unique($groups);

// -----------------------------------------------

function addBan(PDO $db, $banned, $criteria): bool {
    $stm = $db->prepare('SELECT id FROM ban WHERE criteria = :criteria;');
    $stm->bindValue(':criteria', $criteria);
    if (!$stm->execute()) {
        error_log('ban query failed');
        return $banned;
    }
    if ($stm->fetch()) {
        return true;
    }
    return $banned;
}

$banned = false;
$banned = addBan($db, $banned, 'charid_' . $charid);
//$banned = addBan($db, $banned, 'corpid_' . $corpid);
//$banned = addBan($db, $banned, 'allianceid_' . $allianceid);
foreach ($tags as $tkey => $tvalue) {
    $banned = addBan($db, $banned, 'tag_' . $tvalue);
}

if ($banned) {
    $groups = array('user');
}

// -----------------------------------------------

$stm = $db->prepare('SELECT charid FROM user WHERE charid = :charid;');
$stm->bindValue(':charid', $charid);
if (!$stm->execute()) {
    error_log('user query failed');
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
    exit;
}

$stm = $db->prepare('DELETE from session where sessionid = :sessionid;');
$stm->bindValue(':sessionid', $session->getId());
if (!$stm->execute()) {
    error_log('session cleanup failed');
    exit;
}

$stm = $db->prepare('INSERT INTO session (sessionid, charid, created) VALUES (:sessionid, :charid, :created)');
$stm->bindValue(':sessionid', $session->getId());
$stm->bindValue(':charid', $charid);
$stm->bindValue(':created', time());
if (!$stm->execute()) {
    error_log('session insert failed');
    exit;
}

// -----------------------------------------------

$cb = $session->getSegment('Bravecollective_Neucore')->get('cb');

header("Location: " . '/' . $cb);
