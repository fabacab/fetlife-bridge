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
    var $fl_nick; // FetLife nickname for current user.
    var $fl_pw; // FetLife password for current user.
    var $fl_id; // FetLife user ID for current user.
    var $cookiejar; // File to store new/updated cookies for current user.

    /**
     * Get required variables and whatnot when loading.
     *
     * @return bool True! :)
     */
    function initialize ()
    {
        if (!$fl_ini = @parse_ini_file( INSTALLDIR . '/local/FetLifeBridge/fetlifesettings.ini', true ))
        {
            throw new Exception('Failed to load FetLife Bridge configuration. Sure the file exists?');
        }
        else
        {
            $dir = dirname(__FILE__);
            $user = common_current_user();
            if (array_key_exists($user->nickname, $fl_ini))
            {
                $this->fl_nick = $fl_ini[$user->nickname]['fetlife_nickname'];
                $this->fl_pw = $fl_ini[$user->nickname]['fetlife_password'];
                $this->cookiejar = "$dir/fl_sessions/{$this->fl_nick}";
            }
        }

        return true;
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

        if (NULL === $this->fl_nick) {
            return true; // bail out and give other plugins a chance
        }

        // Get a valid cookie if we don't have one yet.
        if (!$this->haveValidFetLifeSession()) {
            $fl_id = $this->obtainFetLifeSession();
        }

        // Prepare to use this cookie.
        //$cookie_data = file_get_contents($cookiejar);

        $post_data   = $this->prepareForFetLife($notice->content);
        // TODO: Variablize and store this on a per-StatusNet user basis.
        //       For now, uncomment any of these valid cookies to make it work.
        //$cookie_data = '_FetLife_session=BAh7CzoQX2NzcmZfdG9rZW4iMXpVb1pBT0R3OGlldGpCUEY4d0tkaEdHU2ZIczhGY2t2djQxMmhDdkZwaFk9Og9zZXNzaW9uX2lkIiU2ZjNmNGI4MDMyZjJjNDE2OTNjZjIxNmRkN2RiMDFmMDoUYWJpbmdvX2lkZW50aXR5bCsHilO1ZToLbG9nX2lwVCIKZmxhc2hJQzonQWN0aW9uQ29udHJvbGxlcjo6Rmxhc2g6OkZsYXNoSGFzaHsGOgtub3RpY2UiHllvdSBoYXZlIGJlZW4gbG9nZ2VkIG91dC4GOgpAdXNlZHsGOwpUOhRjdXJyZW50X3VzZXJfaWRpA0CYDg%3D%3D--d831a7d5aa01de113fac75c65761f7781892a347';
        //$cookie_data = '_FetLife_session=BAh7CToUYWJpbmdvX2lkZW50aXR5bCsHDumTyDoPc2Vzc2lvbl9pZCIlZTUyNDYzZDUyZDE3NWJjZWZiZjIxMzA1YjIwNjI3ODA6FGN1cnJlbnRfdXNlcl9pZGkDQJgOOhBfY3NyZl90b2tlbiIxbVQzQkVXUnF6VEdMVE42amgvb0c0dnZzNmRETkRkc2NWNzg2WG9zckxTZz0%3D--ad77349123e76206e3761f2fbd97e350bfd4f869';

        $ch = curl_init("http://fetlife.com/users/$fl_id/statuses.json");

        // Set cURL options.
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_COOKIE, $cookie_data);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiejar); // save session cookies
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $r = array();
        $r['result'] = json_decode(curl_exec($ch));
        $r['status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        // Uncomment to debug result.
        //$this->logme($r);

        return true;
    }

    /**
     * Lookup FetLife session for the current user, and test it.
     *
     * @return mixed Session string if exists and valid, false otherwise.
     */
    private function haveValidFetLifeSession ()
    {
        if (!$c = file_get_contents($this->cookiejar)) {
            return $c; // False-equivalent is not valid.
        } else {
            // TODO: Return the valid session we have.
        }

    }

    /**
     * Grab a new FetLife session and save it in the cookie jar.
     *
     * @param $user The user object from StatusNet.
     * @return mixed FetLife user ID (integer) on success, false otherwise.
     */
    private function obtainFetLifeSession ()
    {
        // Set up login credentials.
        $post_data = http_build_query(array(
            'nickname_or_email' => $this->fl_nick,
            'password' => $this->fl_pw,
            'commit' => 'Login+to+FetLife' // Emulate pushing the "Login to FetLife" button.
        ));

        // Login to FetLife.
        $ch = curl_init('https://fetlife.com/session');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Save the cookie we get.

        curl_close($ch);

        // Return the FetLife user ID we get.

    }


//    public function getFetLifeInfoForUser ()
//    {
//        $user = common_current_user();
//        if (array_key_exists($user->nickname, $this->flsettings)) {
//            return array(
//                'fetlife_id' => $this->flsettings[$user->nickname]['fetlife_id'],
//                'fetlife_password' => $this->flsettings[$user->nickname]['fetlife_password']
//            );
//        }
//    }

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

        switch ($cls)
        {
            case 'FetlifesettingsAction':
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
