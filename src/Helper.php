<?php

namespace Brave\CoreConnector;

use Brave\NeucoreApi\Api\ApplicationApi;
use Brave\NeucoreApi\ApiException;
use Brave\NeucoreApi\Model\Group;
use Brave\Sso\Basics\EveAuthentication;
use PDO;
use stdClass;

class Helper
{
    public function getCoreGroups(ApplicationApi $applicationApi, EveAuthentication $eveAuthentication): array
    {
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

    public function addGroup(PDO $db, $groups, $criteria): array {
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

    public function addBan(PDO $db, $banned, $criteria): bool {
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
}
