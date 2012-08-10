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

require_once(dirname(__FILE__) . '/lib/FetLife.php');

/**
 * Plugin for sending StatusNet notices as FetLife statuses
 *
 * This class allows users to link their FetLife account
 *
 * @category Plugin
 * @author    Meitar Moscovitz <meitar@maymay.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @link     http://FetLife.com/
 */
class FetLifeBridgePlugin extends Plugin
{
    var $fl_nick;     // FetLife nickname for current user.
    var $fl_pw;       // FetLife password for current user.
    var $fl_ini_path; // File path where FetLife settings are stored.

    /**
     * Get required variables and whatnot when loading.
     *
     * @return bool True! :)
     */
    function initialize ()
    {
        $x = new FetLifeBridgePluginHelper();
        $x->initialize();
        $this->fl_nick = $x->fl_nick;
        $this->fl_pw = $x->fl_pw;
        $this->fl_ini_path = $x->fl_ini_path;
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

        // TODO: Oh my god, refactor this.

        if (empty($this->fl_nick) || empty($this->fl_pw)) {
            return true; // bail out and give other plugins a chance
        }

        $FL = new FetLifeUser($this->fl_nick, $this->fl_pw);
        $FL->logIn();

        // If we still don't have an ID, no point in failing to send a status.
        if (!$FL->id) {
            common_log(1, "Failed to get correct FetLife ID for FetLife nickname '{$this->fl_nick}'. Make sure your FetLife settings are correct?");
            return true;
        }

        $post_data = $this->prepareForFetLife($notice);

        // "Cross-post" notice to FetLife.
        $r = $this->sendToFetLife($post_data, $FL);

        // Make a note of HTTP failure, if we encounter it.
        // TODO: Flesh out this error handling, eventually.
        if (302 === $r['status']) {
            common_log(1, "Attempted to send notice to FetLife, but encountered HTTP error: {$r['status']}.");
        } else if (200 !== $r['status']) {
            common_log(1, "Attempted to send notice to FetLife, but encountered HTTP error: {$r['status']}");
        }

        // Uncomment to debug result.
//        common_log(1, $r);
//        $this->logme($r);

        return true;
    }

    /**
     * Prepare notice to be sent to FetLife. Ensure it meets expectations.
     *
     * @param object Notice $notice A StatusNet Notice object.
     * @return string URL-encoded data ready to be POST'ed to FetLife.
     */
    function prepareForFetLife ($notice) {
        $fl_notice = new FetLifeStatus($notice->content);

        // Limit $notice->content length to 200 chars; FetLife barfs on 201.
        $x = mb_strlen($fl_notice->text);
        if ($fl_notice::MAX_STATUS_LENGTH < $x) {
            $y = mb_strlen($notice->uri);
            // Truncate the notice content so it and its link back URI fit
            // within 200 chars. Include room for an ellipsis and a space char.
            $fl_notice->text = urlencode(mb_substr($fl_notice->text, 0, $fl_notice::MAX_STATUS_LENGTH - 2 - $y));
            $fl_notice->text .= '%E2%80%A6+'; // urlencode()'d ellipsis and space character.
            $fl_notice->text .= urlencode($notice->uri);
        } else {
            $fl_notice->text = urlencode($fl_notice->text);
        }
        return urlencode('status[body]=') . $fl_notice->text;
    }

    /**
     * Cross-post notice to FetLife.
     *
     * @param string $post_data A string prepared with `$this->prepareForFetLife()`.
     * @param object $usr A FetLifeUser object from the libFetLife library.
     * @return array $r
     * @see prepareForFetLife()
     */
    function sendToFetLife ($post_data, $usr) {

        $ch = curl_init("https://fetlife.com/users/{$usr->id}/statuses.json");

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $usr->connection->cookiejar); // use session cookies
        curl_setopt($ch, CURLOPT_COOKIEJAR, $usr->connection->cookiejar); // save session cookies
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Csrf-Token: ' . $usr->connection->csrf_token));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $r = array();

        $r['result'] = json_decode(curl_exec($ch));
        $r['status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $r['all']    = curl_getinfo($ch);

        curl_close($ch);

        return $r;
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

/**
 * This exists to avoid re-initializing the main plugin from the settings.
 * TODO: Find a better way to do this, eh?
 */
class FetLifeBridgePluginHelper {

    var $fl_nick;     // FetLife nickname for current user.
    var $fl_pw;       // FetLife password for current user.
    var $fl_ini_path; // File path where FetLife settings are stored.

    function initialize () {
        $dir = dirname(__FILE__);
        $this->fl_ini_path = "$dir/fetlifesettings.ini";
        $user = common_current_user();

        // Check to ensure configuration file is available and usable.
        if (!$fl_ini = @parse_ini_file($this->fl_ini_path, true)) {
            if (touch($this->fl_ini_path)) {
                // Can write to file. Create a default.
                $initxt  = "; This is an automatically generated file. Edit with care.\n";
                $initxt .= "[{$user->nickname}]\n";
                $initxt .= "fetlife_nickname = \"\"\n";
                $initxt .= "fetlife_password = \"\"\n";
                file_put_contents($this->fl_ini_path, $initxt) OR common_log(1, "Can't write to {$this->fl_ini_path}. Either configure manually or ensure this file is writable by your webserver.");
            } else {
                common_log(1, "Failed to load FetLife Bridge configuration. Sure {$this->fl_ini_path} file exists and is writable by the webserver?");
            }
        } else {
            if (array_key_exists($user->nickname, $fl_ini))
            {
                $this->fl_nick = $fl_ini[$user->nickname]['fetlife_nickname'];
                $this->fl_pw = $fl_ini[$user->nickname]['fetlife_password'];
            }
        }

        return true;
    }

}
