<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Plugin
 * @author    Meitar Moscovitz <meitar@maymay.net>
 * @copyright 2011 Meitar Moscovitz
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://maymay.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin for sending StatusNet notices as FetLife statuses
 *
 * This class allows users to link their Twitter accounts
 *
 * @category Plugin
 * @author    Meitar Moscovitz <meitar@maymay.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @link     http://FetLife.com/
 */
class FetLifeBridgePlugin extends Plugin
{
    var $flsettings;

    function initialize ()
    {
        // Read in configuration.
        $this->flsettings = parse_ini_file( INSTALLDIR . '/local/FetLifeBridge/fetlifesettings.ini', true );
    }

    /**
     * Send notice to FetLife after it's saved.
     *
     * @param Notice $notice Notice that was saved
     *
     * @return boolean hook value
     */
    function onEndNoticeSave ($notice)
    {

        $user = common_current_user();

        if (!array_key_exists($user->nickname, $this->flsettings))
        {
            return false; // this user is not configured for FetLife
        }
        else
        {
            $fl_id = $this->flsettings[$user->nickname]['fetlife_id'];
            $fl_pw = $this->flsettings[$user->nickname]['fetlife_password'];
        }



        $post_data   = $this->prepareForFetLife($notice->content);
        // TODO: Variablize and store this on a per-StatusNet user basis.
        //       For now, uncomment any of these valid cookies to make it work.
        //$cookie_data = '_FetLife_session=BAh7CzoQX2NzcmZfdG9rZW4iMXpVb1pBT0R3OGlldGpCUEY4d0tkaEdHU2ZIczhGY2t2djQxMmhDdkZwaFk9Og9zZXNzaW9uX2lkIiU2ZjNmNGI4MDMyZjJjNDE2OTNjZjIxNmRkN2RiMDFmMDoUYWJpbmdvX2lkZW50aXR5bCsHilO1ZToLbG9nX2lwVCIKZmxhc2hJQzonQWN0aW9uQ29udHJvbGxlcjo6Rmxhc2g6OkZsYXNoSGFzaHsGOgtub3RpY2UiHllvdSBoYXZlIGJlZW4gbG9nZ2VkIG91dC4GOgpAdXNlZHsGOwpUOhRjdXJyZW50X3VzZXJfaWRpA0CYDg%3D%3D--d831a7d5aa01de113fac75c65761f7781892a347';
        //$cookie_data = '_FetLife_session=BAh7CToUYWJpbmdvX2lkZW50aXR5bCsHDumTyDoPc2Vzc2lvbl9pZCIlZTUyNDYzZDUyZDE3NWJjZWZiZjIxMzA1YjIwNjI3ODA6FGN1cnJlbnRfdXNlcl9pZGkDQJgOOhBfY3NyZl90b2tlbiIxbVQzQkVXUnF6VEdMVE42amgvb0c0dnZzNmRETkRkc2NWNzg2WG9zckxTZz0%3D--ad77349123e76206e3761f2fbd97e350bfd4f869';

        $ch = curl_init("http://fetlife.com/users/$fl_id/statuses.json");

        // Set cURL options.
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_COOKIE, $cookie_data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $r = array();
        $r['result'] = curl_exec($ch);
        $r['status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        // Uncomment to debug result.
        //$this->logme($r);

    }

    private function prepareForFetLife ($notice_content)
    {
        return urlencode("status[body]=$notice_content");
    }

    /**
     * Add FetLife-related paths to the router table
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     * @return boolean hook return
     */
    function onRouterInitialized($m)
    {
        $m->connect('settings/fetlife', array('action' => 'fetlifesettings'));
        return true;
    }

    /**
     * Add the FetLife Settings page to the Account Settings menu
     *
     * @param Action $action The calling page
     * @return boolean hook return
     */
    function onEndAccountSettingsNav ($action)
    {
        $action_name = $action->trimmed('action');

        $action->menuItem(
            common_local_url('fetlifesettings'),
            'FetLife',
            'FetLife integration options',
            'fetlifesettings' === $action_name
        );
        return true;
    }

    /**
     * Automatically load the actions and libraries used by the FetLife bridge
     *
     * @param Class @cls the class
     * @return boolean hook return
     */
    function onAutoload ($cls)
    {
        $dir = dirname(__FILE__);

        // TODO: Why isn't my action getting called from here?
        //$this->logme($cls);
        switch ($cls) {
            case 'FetLifeSettingsAction':
                include_once $dir . '/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
                return false;
            default:
                return true;
        }

    }


    // My own little var_dump() replacement.
    function logme($x)
    {
        ob_start();
        var_dump($x);
        $y = ob_get_contents();
        ob_end_clean();
        common_log(1, $y);
    }
}
