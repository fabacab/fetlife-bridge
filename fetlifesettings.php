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

/**
 * Settings for FetLife integration
 *
 * @category  Settings
 * @author    Meitar Moscovitz <meitar@maymay.net>
 * @link      http://maymay.net/
 * @see       SettingsAction
 */
class FetlifesettingsAction extends SettingsAction
{

    var $flbp;

    function prepare ($args) {
        parent::prepare($args);

        // TODO: Instantiating a new object here causes interface issues.
        //       Find out how I can access variables from the other class
        //       when this action runs. For now, deal with the UI issue.
        $this->flbp = new FetLifeBridgePluginHelper();
        $this->flbp->initialize();

        return true;
    }

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
        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'form_settings_fetlife',
                                           'class' => 'form_settings',
                                           'action' =>
                                           common_local_url('fetlifesettings')));

        $this->hidden('token', common_session_token());

        $this->elementStart('fieldset', array('id' => 'settings_fetlife_account'));

        $this->elementStart('ul', array('class' => 'form_data'));

        $this->elementStart('li');
        $this->element('label', array('for' => 'fetlife_nickname'), _m('FetLife username'));
        $this->element('input', array('id' => 'fetlife_nickname', 'name' => 'fetlife_nickname',
                                      'value' => $this->flbp->fl_nick));
        $this->elementEnd('li');

        $this->elementStart('li');
        $this->element('label', array('for' => 'fetlife_password'), _m('FetLife password'));
        $this->element('input', array('id' => 'fetlife_password', 'name' => 'fetlife_password',
                                      'type' => 'password', 'value' => $this->flbp->fl_pw));
        $this->elementEnd('li');

        $this->elementStart('li');
        $this->submit('save', _m('Save'));
        $this->elementEnd('li');

        $this->elementEnd('ul');
        $this->elementEnd('fieldset');

        $this->elementEnd('form');
    }

    function handlePost ()
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
        $user = common_current_user();
        $this->flbp->fl_nick = $this->trimmed('fetlife_nickname');
        $this->flbp->fl_pw   = $this->trimmed('fetlife_password');
        $fl_settings = array(
            "{$user->nickname}" => array(
                'fetlife_nickname' => $this->flbp->fl_nick,
                'fetlife_password' => $this->flbp->fl_pw
            )
        );

        // TODO: Figure out how to actually use the FetLifeBridgePlugin::$fl_ini_path variable correctly. :\
        $old_fl_settings = parse_ini_file(dirname(__FILE__) . '/fetlifesettings.ini', true);
        // TODO: Using the array union operator is crude, but it works. For now.
        $this->write_ini_file($fl_settings + $old_fl_settings, dirname(__FILE__) . '/fetlifesettings.ini', true);

        $this->showForm(_m('FetLife preferences saved.'), true);
    }

    /**
     * Writes an ini file from an associative array. Slightly modified from PHP documentation:
     *
     * @see http://www.php.net/manual/en/function.parse-ini-file.php#78060
     */
    function write_ini_file($assoc_arr, $path, $has_sections=FALSE) {
        $content = "";
        if ($has_sections) {
            foreach ($assoc_arr as $key=>$elem) {
                $content .= "[".$key."]\n";
                foreach ($elem as $key2=>$elem2) {
                    if(is_array($elem2))
                    {
                        for($i=0;$i<count($elem2);$i++)
                        {
                            $content .= $key2."[] = \"".$elem2[$i]."\"\n";
                        }
                    }
                    else if($elem2=="") $content .= $key2." = \n";
                    else $content .= $key2." = \"".$elem2."\"\n";
                }
            }
        }
        else {
            foreach ($assoc_arr as $key=>$elem) {
                if(is_array($elem))
                {
                    for($i=0;$i<count($elem);$i++)
                    {
                        $content .= $key2."[] = \"".$elem[$i]."\"\n";
                    }
                }
                else if($elem=="") $content .= $key2." = \n";
                else $content .= $key2." = \"".$elem."\"\n";
            }
        }

        if (!$handle = fopen($path, 'w')) {
            return false;
        }
        if (!fwrite($handle, $content)) {
            return false;
        }
        fclose($handle);
        return true;
    }

}
