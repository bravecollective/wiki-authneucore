<?php
/**
 * DokuWiki Plugin authneucore (Auth Component)
 *
 * @license MIT http://opensource.org/licenses/MIT
 * @author  kiu kiu@gmx.net
 * @author  Yukiko yukiko.kami.san@gmail.com
 */

use Brave\CoreConnector\Bootstrap;
use dokuwiki\Extension\AuthPlugin;
use Psr\Container\ContainerExceptionInterface;

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

/** @noinspection PhpUnused */
class auth_plugin_authneucore extends AuthPlugin
{
    private PDO $db;

    private Bootstrap $bootstrap;

    /**
     * @throws ContainerExceptionInterface
     */
    public function __construct()
    {
        define('ROOT_DIR', __DIR__);
        parent::__construct(); // for compatibility
        require ROOT_DIR . '/vendor/autoload.php';
        $this->bootstrap = new Bootstrap();
        $this->db = $this->bootstrap->getContainer()->get(PDO::class);

        $this->cando['external'] = true; // does the module do external auth checking?
        $this->cando['logout'] = true; // can the user logout again? (eg. not possible with HTTP auth)

        // Even though it is possible to store and edit the mail address through this plugin
        // it is considered a security risk as a user could subscribe to a namespace and continue to
        // receive change notifications even though his core account has been revoked.
        $this->cando['modMail'] = false; // can emails be changed?

        $this->cando['addUser'] = false; // can Users be created?
        $this->cando['delUser'] = false; // can Users be deleted?
        $this->cando['modLogin'] = false; // can login names be changed?
        $this->cando['modPass'] = false; // can passwords be changed?
        $this->cando['modName'] = false; // can real names be changed?
        $this->cando['modGroups'] = false; // can groups be changed?
        $this->cando['getUsers'] = false; // can a (filtered) list of users be retrieved?
        $this->cando['getUserCount'] = false; // can the number of users be retrieved?
        $this->cando['getGroups'] = false; // can a list of available groups be retrieved?

        $this->success = true;
    }

    public function logOff(): void
    {
        $stm = $this->db->prepare('DELETE FROM session WHERE sessionid = :sessionid;');
        $stm->bindValue(':sessionid', $this->getSessionId());
        if (!$stm->execute()) {
            die('logoff failed');
        }
    }

    /**
     * Do all authentication [ OPTIONAL ]
     *
     * @param   string $user Username
     * @param   string $pass Cleartext Password
     * @param   bool $sticky Cookie should not expire
     * @return  bool             true on successful auth
     */
    public function trustExternal($user, $pass, $sticky = false): bool
    {
        global $USERINFO;

        $row = $this->getUser();
        if (!$row) {
            return false;
        }

        // set the globals if authed
        $USERINFO['name'] = $row['charname'];
        $USERINFO['mail'] = $row['mail'];
        $USERINFO['grps'] = explode(',', $row['groups']);
        $_SERVER['REMOTE_USER'] = $row['username'];
        $_SESSION[DOKU_COOKIE]['auth']['user'] = $row['username'];
        //        $_SESSION[DOKU_COOKIE]['auth']['pass'] = $pass;
        $_SESSION[DOKU_COOKIE]['auth']['info'] = $USERINFO;

        return true;
    }

    /**
     * Return user info
     *
     * Returns info about the given user needs to contain
     * at least these fields:
     *
     * name string  full name of the user
     * mail string  email addres of the user
     * grps array   list of groups the user is in
     *
     * @param   string $user the user name
     * @return  array|false containing user data or false
     */
    public function getUserData($user, $requireGroups = true): bool|array
    {
        $stm = $this->db->prepare('SELECT * FROM user WHERE username = :username;');
        $stm->bindValue(':username', $user);
        if (!$stm->execute()) {
            die('user search failed');
        }

        $row = $stm->fetch();
        if (!$row) {
            return false;
        }

        return ['name' => $row['charname'], 'email' => $row['mail'], 'grps' => []];
    }

    /**
     * Modify user data [implement only where required/possible]
     *
     * Set the mod* capabilities according to the implemented features
     *
     * @param   string $user nick of the user to be changed
     * @param   array $changes array of field/value pairs to be changed (password will be clear text)
     */
    public function modifyUser($user, $changes): bool
    {
        // $stm = $this->db->prepare('UPDATE user SET mail = :mail WHERE username = :username;');
        // $stm->bindValue(':username', $user);
        // $stm->bindValue(':mail', $changes['mail']);
        // if (!$stm->execute()) { die('user modification failed'); };
        // return true;
        return false;
    }

    /**
     * Return case sensitivity of the backend
     *
     * When your backend is caseinsensitive (eg. you can login with USER and
     * user) then you need to overwrite this method and return false
     */
    public function isCaseSensitive(): bool
    {
        return true;
    }

    /**
     * Sanitize a given username
     *
     * This function is applied to any user name that is given to
     * the backend and should also be applied to any user name within
     * the backend before returning it somewhere.
     *
     * This should be used to enforce username restrictions.
     *
     * @param string $user username
     * @return string the cleaned username
     */
    public function cleanUser($user): string
    {
        return preg_replace("/[^A-Za-z0-9]/", '_', $user);
    }

    /**
     * Sanitize a given groupname
     *
     * This function is applied to any groupname that is given to
     * the backend and should also be applied to any groupname within
     * the backend before returning it somewhere.
     *
     * This should be used to enforce groupname restrictions.
     *
     * Groupnames are to be passed without a leading '@' here.
     *
     * @param  string $group groupname
     * @return string the cleaned groupname
     */
    public function cleanGroup($group): string
    {
        return preg_replace("/[^A-Za-z0-9]/", '_', $group);
    }

    /**
     * Check Session Cache validity [implement only where required/possible]
     *
     * DokuWiki caches user info in the user's session for the timespan defined
     * in $conf['auth_security_timeout'].
     *
     * This makes sure slow authentication backends do not slow down DokuWiki.
     * This also means that changes to the user database will not be reflected
     * on currently logged in users.
     *
     * To accommodate for this, the user manager plugin will touch a reference
     * file whenever a change is submitted. This function compares the filetime
     * of this reference file with the time stored in the session.
     *
     * This reference file mechanism does not reflect changes done directly in
     * the backend's database through other means than the user manager plugin.
     *
     * Fast backends might want to return always false, to force rechecks on
     * each page load. Others might want to use their own checking here. If
     * unsure, do not override.
     *
     * @param  string $user - The username
     */
    public function useSessionCache($user): bool
    {
        return false;
    }

    private function getSessionId(): string
    {
        // Return session ID from the previous session (from core_init.php and core_success.php).
        // Note: session_id() does not work here.
        return (string)preg_replace("/[^A-Za-z0-9]/", '', $_COOKIE['authneucore'] ?? null);
    }

    private function getUser()
    {
        $stm = $this->db->prepare('DELETE FROM session WHERE created < :time;');
        $stm->bindValue(':time', time() - 28800); // 8 hours
        if (!$stm->execute()) {
            die('cleanup session failed');
        }

        $stm = $this->db->prepare('SELECT charid FROM session WHERE sessionid = :sessionid;');
        $stm->bindValue(':sessionid', $this->getSessionId());
        if (!$stm->execute()) {
            die('find session failed');
        }

        $row = $stm->fetch();
        if (!$row) {
            return false;
        }

        $stm = $this->db->prepare('SELECT * FROM user WHERE charid = :charid;');
        $stm->bindValue(':charid', $row['charid']);
        if (!$stm->execute()) {
            die('find user failed');
        }

        $row = $stm->fetch();
        if (!$row) {
            return false;
        }

        // refresh session time in database
        $stm = $this->db->prepare('UPDATE session SET created = :created WHERE sessionid = :sessionid');
        $stm->bindValue(':created', time());
        $stm->bindValue(':sessionid', $this->getSessionId());
        $stm->execute();

        // refresh session file time so that the Ubuntu cron job does not delete it
        // after "session.gc_maxlifetime" seconds
        $_SESSION[DOKU_COOKIE]['auth']['_last_access'] = time();

        return $row;
    }
}
