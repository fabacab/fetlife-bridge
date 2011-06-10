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

            // Configure FetLife session store directory.
            if (!file_exists("$dir/fl_sessions")) {
                if (!mkdir("$dir/fl_sessions", 0700)) {
                    throw new Exception('Failed to create FetLife Sessions store directory.');
                }
            }

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

        // See if we've got a valid session, and grab a new one if not.
        if (!$fl_id = $this->haveValidFetLifeSession()) {
            $fl_id = $this->obtainFetLifeSession($this->fl_nick, $this->fl_pw);
        }

        // Prepare notice for FetLife.
        $post_data = urlencode("status[body]={$notice->content}");

        // "Cross-post" notice to FetLife.
        $ch = curl_init("http://fetlife.com/users/$fl_id/statuses.json");

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookiejar);
        //curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookiejar); // save session cookies
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
     */
    private function haveValidFetLifeSession ()
    {
        if (!file_exists($this->cookiejar)) {
            return false;
        } else {
            return $this->testFetLifeSession($this->cookiejar);
        }
    }

    /**
     * Tests the current cookiejar to ensure it's valid.
     *
     * @param string $fl_sess The FetLife sesssion to test. Currently must be a filepath to a cURL cookiefile.
     * @return mixed FetLife user ID on success, false otherwise.
     */
    private function testFetLifeSession ($fl_sess)
    {
        $ch = curl_init('http://fetlife.com/home');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // TODO: Branch to determine what kind of $fl_sess data we've got.
        //       For now ASSUME PATH TO COOKIEFILE.
        curl_setopt($ch, CURLOPT_COOKIEFILE, $fl_sess);

        $fetlife_html = curl_exec($ch);

        // TODO: Flesh out some of this error handling stuff.
        if (curl_errno($ch)) {
            return false; // Some kind of error with cURL.
        } else {
            return $this->findFetLifeUserId($fetlife_html); // Might also be false?
        }

    }

    /**
     * Grab a new FetLife session cookie via FetLife.com login form,
     * and saves it in the cookie jar.
     *
     * @param string $nick_or_email The nickname or email address used for FetLife.
     * @param string $password The FetLife password.
     * @return mixed FetLife user ID (integer) on success, false otherwise.
     */
    private function obtainFetLifeSession ($nick_or_email, $password)
    {
        // Set up login credentials.
        $post_data = http_build_query(array(
            'nickname_or_email' => $nick_or_email,
            'password' => $password,
            'commit' => 'Login+to+FetLife' // Emulate pushing the "Login to FetLife" button.
        ));

        // Login to FetLife.
        $ch = curl_init('https://fetlife.com/session');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookiejar); // save session cookies
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $fetlife_html = curl_exec($ch); // Grab FetLife HTML page.

        curl_close($ch);

        // TODO: Flesh out some of this error handling stuff.
        if (curl_errno($ch)) {
            return false; // Some kind of error with cURL.
        } else {
            return $this->findFetLifeUserId($fetlife_html); // Might also be false?
        }

    }

    /**
     * Given some HTML from FetLife, this finds the current user ID.
     *
     * @param string $str Some raw HTML expected to be from FetLife.com.
     * @return mixed User ID on success. False on failure.
     */
    private function findFetLifeUserId ($str) {
        $matches = array();
        preg_match('/var currentUserId = ([0-9]+);/', $str, $matches);
        return $matches[1];
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
