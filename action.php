<?php

class action_plugin_authneucore extends DokuWiki_Action_Plugin
{

    /** @inheritdoc */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('HTML_LOGINFORM_OUTPUT', 'BEFORE', $this, 'handle_loginform');
    }

    /**
     * Disable the login forma and instead use a link to trigger login
     *
     * @param Doku_Event $event
     * @noinspection PhpUnused
     */
    public function handle_loginform(Doku_Event $event)
    {
        global $conf;
        if ($conf['authtype'] != 'authneucore') {
		    return;
        }

        $config = include __DIR__ . '/config/config.php';

        $loginButtonUrl = '/lib/plugins/authneucore/images/EVE_SSO_Login_Buttons_Large_Black.png';
        $button = '<button type="submit"><img src="'.$loginButtonUrl.'" alt=""/></button>';

        $pos = $event->data->findElementByAttribute('type', 'submit');

        $event->data->replaceElement($pos-1, $button);

        $event->data = new Doku_Form(array());
        $event->data->addElement(
            '<a href="'.$config['wiki.baseUrl'].'lib/plugins/authneucore/core_init.php?cb=' .
            ltrim($_SERVER['REQUEST_URI'], '/') . '"><img src="'.$loginButtonUrl.'" alt=""></a>'
        );
    }
}
