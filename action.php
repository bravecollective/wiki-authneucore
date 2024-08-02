<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

/** @noinspection PhpUnused */
class action_plugin_authneucore extends ActionPlugin
{
    public function register(EventHandler $controller): void
    {
        $controller->register_hook('FORM_LOGIN_OUTPUT', 'BEFORE', $this, 'handle_loginform');
    }

    /**
     * Disable the login forma and instead use a link to trigger login
     */
    public function handle_loginform(Event $event): void
    {
        global $conf;
        if ($conf['authtype'] != 'authneucore') {
		    return;
        }

        $config = include __DIR__ . '/config/config.php';

        $loginButtonUrl = '/lib/plugins/authneucore/images/EVE_SSO_Login_Buttons_Large_Black.png';
        
        // We need to delete the existing elements from the login form.
        $positionRange = range(($event->data->elementCount() - 1), 0);
        
        foreach ($positionRange as $eachPosition) {
            $event->data->removeElement($eachPosition);
        }

        // And now we add our custom login button.
        $event->data->addHTML(
            '<br><a href="'.$config['wiki.baseUrl'].'lib/plugins/authneucore/core_init.php?cb=' .
            ltrim($_SERVER['REQUEST_URI'], '/') . '"><img src="'.$loginButtonUrl.'" alt=""></a>'
        );
    }
}
