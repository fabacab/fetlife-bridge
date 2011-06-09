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
class FetlifesettingsAction extends AcountSettingsAction
{
    function title ()
    {
        return 'FetLife settings';
    }

    function getInstructions () 
    {
        return 'Connect your FetLife account to share your updates ' .
                'with your FetLife friends.';
    }

    function showContent ()
    {

        $user = common_current_user();
        $profile = $user->getProfile();

        $this->element('a', array('href' => 'http://fetlife.com/'), 'FetLife.com!');

    }

    function handlePost()
    {
    }

    function savePreferences ()
    {
    }
}
