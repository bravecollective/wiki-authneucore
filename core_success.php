<?php
require('vendor/autoload.php');
define('ROOT_DIR', __DIR__);

$bootstrap = new \Brave\CoreConnector\Bootstrap();
/** @var \Brave\Sso\Basics\AuthenticationProvider $authenticationProvider */
$authenticationProvider = $bootstrap->getContainer()->get(\Brave\Sso\Basics\AuthenticationProvider::class);

/** @var \Aura\Session\Session $session */
$session = $bootstrap->getContainer()->get(\Aura\Session\Session::class);
$sessionState = $session->getSegment('Bravecollective_Neucore')->get('sso_state');

if (!isset($_GET['code']) || !isset($_GET['state'])) {
    echo 'Invalid SSO state, please try again.';
    exit;
}

$code = $_GET['code'];
$state = $_GET['state'];

$authenticationProvider = $bootstrap->getContainer()->get(\Brave\Sso\Basics\AuthenticationProvider::class);
$eveAuthentication = $authenticationProvider->validateAuthentication($state, $sessionState, $code);

$session->getSegment('Bravecollective_Neucore')->set('eveAuth', $eveAuthentication);

/** @var \Brave\NeucoreApi\Api\ApplicationApi $applicationApi */
$applicationApi = $bootstrap->getContainer()->get(\Brave\NeucoreApi\Api\ApplicationApi::class);

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
    \Brave\NeucoreApi\Api\ApplicationApi $applicationApi,
    \Brave\Sso\Basics\EveAuthentication $eveAuthentication
) {
    $charId = $eveAuthentication->getCharacterId();
    $coreGroups = [];

    // try player account
    try {
        $coreGroups = $applicationApi->groupsV2($charId);
    } catch (\Brave\NeucoreApi\ApiException $e) {
        // probably 404 Character not found
    }

    // try alliance groups
    if (count($coreGroups) === 0) {
        $esiResult = file_get_contents('https://esi.evetech.net/latest/characters/' . $charId);
        $charData = json_decode($esiResult);
        if ($charData instanceof \stdClass) {
            try {
                $coreGroups = $applicationApi->allianceGroupsV2((int) $charData->alliance_id);
            } catch (\Brave\NeucoreApi\ApiException $e) {
                // probably 404 Alliance not found
            }
        }
    }

    $tags = array_map(function (\Brave\NeucoreApi\Model\Group $group) {
        return $group->getName();
    }, $coreGroups);

    return $tags;
}

$tags = getCoreGroups($applicationApi, $eveAuthentication);
if (count($tags) === 0) {
    echo '<strong>No groups found for this character or alliance.</strong><br><br>',
        'Please register at <a href="https://account.bravecollective.com">BRAVE Core</a>. ',
        'If groups are listed on the right, try again here.<br><br>' .
        'If your alliance is a member of the Legacy Coalition, you should have access, maybe ESI is down?';
    exit;
}

// -----------------------------------------------

/** @var $db \PDO  */
$db = $bootstrap->getContainer()->get(\PDO::class);

// -----------------------------------------------

function addGroup(\PDO $db, $groups, $criteria) {
    $stm = $db->prepare('SELECT grp FROM grp WHERE criteria = :criteria;');
    $stm->bindValue(':criteria', $criteria);
    if (!$stm->execute()) { raiseError('group query failed'); };
    while($res = $stm->fetch()){
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

function addBan(\PDO $db, $banned, $criteria) {
    $stm = $db->prepare('SELECT id FROM ban WHERE criteria = :criteria;');
    $stm->bindValue(':criteria', $criteria);
    if (!$stm->execute()) { raiseError('ban query failed'); };
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

$stm = $db->prepare('SELECT charid FROM user where charid = :charid;');
$stm->bindValue(':charid', $charid);
if (!$stm->execute()) { raiseError('user query failed'); };

if ($stm->fetch()) {
    $stm = $db->prepare('UPDATE user SET username = :username, groups = :groups, charname = :charname, corpid = :corpid, corpname = :corpname, allianceid = :allianceid, alliancename = :alliancename, authtoken = :authtoken, authlast = :now WHERE charid = :charid;');
} else {
    $stm = $db->prepare('INSERT INTO user (username, groups, charid, charname, corpid, corpname, allianceid, alliancename, authtoken, authcreated, authlast) VALUES (:username, :groups, :charid, :charname, :corpid, :corpname, :allianceid, :alliancename, :authtoken, :now, :now);');
}
$stm->bindValue(':username', $username, PDO::PARAM_STR);
$stm->bindValue(':groups', implode(',', $groups), PDO::PARAM_STR);
$stm->bindValue(':charid', $charid, PDO::PARAM_INT);
$stm->bindValue(':charname', $charname, PDO::PARAM_STR);
$stm->bindValue(':corpid', $corpid, PDO::PARAM_INT);
$stm->bindValue(':corpname', $corpname, PDO::PARAM_STR);
$stm->bindValue(':allianceid', $allianceid, PDO::PARAM_INT);
$stm->bindValue(':alliancename', $alliancename, PDO::PARAM_STR);
$stm->bindValue(':authtoken', $token, PDO::PARAM_STR);
$stm->bindValue(':now', time(), PDO::PARAM_INT);
if (!$stm->execute()) { raiseError('user insert or update failed'); };

$stm = $db->prepare('DELETE from session where sessionid = :sessionid;');
$stm->bindValue(':sessionid', $session->getId());
if (!$stm->execute()) { raiseError('session cleanup failed'); };

$stm = $db->prepare('INSERT INTO session (sessionid, charid, created) VALUES (:sessionid, :charid, :created)');
$stm->bindValue(':sessionid', $session->getId());
$stm->bindValue(':charid', $charid);
$stm->bindValue(':created', time());
if (!$stm->execute()) { raiseError('session insert failed'); };

// -----------------------------------------------

$cb = $session->getSegment('Bravecollective_Neucore')->get('cb');

header("Location: " . $cfg_url_base . '/' . $cb);
