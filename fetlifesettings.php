<?php
/**
 * Settings for FetLife integration
 *
 * PHP version 5
 *
 * @category  Settings
 * @author    Meitar Moscovitz <meitar@maymay.net>
 * @copyright 2011 Meitar Moscovitz
 * @link      http://maymay.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/accountsettingsaction.php';

/**
 * Settings for FetLife integration
 *
 * @category  Settings
 * @author    Meitar Moscovitz <meitar@maymay.net>
 * @link      http://maymay.net/
 * @see       SettingsAction
 */
class FetlifesettingsAction extends AccountSettingsAction
{
    function title ()
    {
        return _m('FetLife settings');
    }

    function getInstructions () 
    {
        return _m('Connect your FetLife account to share your updates ' .
                'with your FetLife friends.');
    }

    function showContent ()
    {

        $user = common_current_user();
        $profile = $user->getProfile();
        
        //FetLifeBridgePlugin::logme($profile);

        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'form_settings_fetlife',
                                           'class' => 'form_settings',
                                           'action' =>
                                           common_local_url('fetlifesettings')));

        $this->hidden('token', common_session_token());

        $this->elementStart('fieldset', array('id' => 'settings_fetlife_account'));

        $this->elementStart('ul');

        // TODO: Pull values out of the database?
        $this->elementStart('li');
        $this->element('label', array('for' => 'fetlife_id'), _m('FetLife ID'));
        $this->element('input', array('id' => 'fetlife_id', 'value' => ''));
        $this->elementEnd('li');

        $this->elementStart('li');
        $this->element('label', array('for' => 'fetlife_password'), _m('FetLife password'));
        $this->element('input', array('id' => 'fetlife_password', 'type' => 'password', 'value' => ''));
        $this->elementEnd('li');

        $this->elementStart('li');
        $this->submit('save', _m('Save'));
        $this->elementEnd('li');

        $this->elementEnd('ul');
        $this->elementEnd('fieldset');

        $this->elementEnd('form');

    }

    function handlePost()
    {
        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_m('There was a problem with your session token. '.
                               'Try again, please.'));
            return;
        }

        if ($this->arg('save')) {
            $this->savePreferences();
        } else {
            $this->showForm(_m('Unexpected form submission.'));
        }
    }

    function savePreferences ()
    {
        // TODO: Save fields to database instead of to file.

        $this->showForm(_m('FetLife preferences saved.'), true);
    }
}
