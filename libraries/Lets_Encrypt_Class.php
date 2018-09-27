<?php

/**
 * Let's Encrypt class.
 *
 * @category   apps
 * @package    lets-encrypt
 * @subpackage controllers
 * @author     eGloo <developer@egloo.ca>
 * @copyright  2017-2018 Marc Laporte
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       https://github.com/eglooca/app-lets-encrypt
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// N A M E S P A C E
///////////////////////////////////////////////////////////////////////////////

namespace clearos\apps\lets_encrypt;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('lets_encrypt');
clearos_load_language('certificate_manager');
clearos_load_language('network');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Classes
//--------

use \clearos\apps\base\Configuration_File as Configuration_File;
use \clearos\apps\base\Daemon as Daemon;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Folder as Folder;
use \clearos\apps\base\Shell as Shell;
use \clearos\apps\base\Software as Software;
use \clearos\apps\certificate_manager\SSL as SSL;
use \clearos\apps\network\Network_Utils as Network_Utils;

clearos_load_library('base/Configuration_File');
clearos_load_library('base/Daemon');
clearos_load_library('base/File');
clearos_load_library('base/Folder');
clearos_load_library('base/Shell');
clearos_load_library('base/Software');
clearos_load_library('certificate_manager/SSL');
clearos_load_library('network/Network_Utils');

// Exceptions
//-----------

use \Exception as Exception;
use \clearos\apps\base\Engine_Exception as Engine_Exception;
use \clearos\apps\base\File_No_Match_Exception as File_No_Match_Exception;
use \clearos\apps\base\File_Not_Found_Exception as File_Not_Found_Exception;
use \clearos\apps\base\Validation_Exception as Validation_Exception;

clearos_load_library('base/Engine_Exception');
clearos_load_library('base/File_No_Match_Exception');
clearos_load_library('base/File_Not_Found_Exception');
clearos_load_library('base/Validation_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Let's Encrypt class.
 *
 * @category   apps
 * @package    lets-encrypt
 * @subpackage controllers
 * @author     eGloo <developer@egloo.ca>
 * @copyright  2017-2018 Marc Laporte
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       https://github.com/eglooca/app-lets-encrypt
 */

class Lets_Encrypt_Class extends Software
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const PATH_CERTIFICATES = '/etc/letsencrypt/live';
    const COMMAND_CERTBOT = '/usr/bin/certbot';
    const FILE_LOG_PREFIX = 'lets-encrypt-';
    const FILE_APP_CONFIG = '/etc/clearos/lets_encrypt.conf';

    ///////////////////////////////////////////////////////////////////////////////
    // V A R I A B L E S
    ///////////////////////////////////////////////////////////////////////////////

    protected $is_loaded = FALSE;
    protected $config = array();
    protected $max_logs = 200;
    public $daemon_list = ['httpd', 'nginx'];

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Let's Encrypt constructor.
     */

    public function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);

        parent::__construct('certbot');
    }

    /**
     * Returns auto-renew state.
     *
     * By default, auto renewals are enabled.  However, this behavior can
     * be changed via configuration file.
     *
     * @return boolean state of auto-renew state
     */

    public function get_auto_renew_state()
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $file = new File(self::FILE_APP_CONFIG);
            $value = $file->lookup_value("/^auto_renew\s*=\s*/i");
        } catch (File_Not_Found_Exception $e) {
            return TRUE;
        } catch (File_No_Match_Exception $e) {
            return TRUE;
        } catch (Exception $e) {
            throw new Engine_Exception($e->get_message());
        }

        if (preg_match('/yes/i', $value))
            return TRUE;
        else
            return FALSE;
    }

    /**
     * Returns the admin e-mail address.
     *
     * @return string admin e-mail address
     * @throws Engine_Exception
     */

    public function get_email()
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->_load_config();

        $email = (empty($this->config['email'])) ? '' : $this->config['email'];

        return $email;
    }

    /**
     * Renews certificates.
     *
     * The basically runs "certbot renew" but does some firewall,
     * Apache, and NGINX checks.
     *
     * @param boolean $auto flag is set if called via default cron
     *
     * @return void
     * @throws Engine_Exception
     */

    public function renew($auto = FALSE)
    {
        clearos_profile(__METHOD__, __LINE__);

        if ($auto && !$this->get_auto_renew_state())
            return;

        if (!$this->renew_required()) {
            clearos_log('lets_encrypt', lang('lets_encrypt_renew_not_required'));
            return;
        }

        // Manage daemons and firewall on port 80
        //---------------------------------------

        $daemon_states = $this->_disengage_daemons();
        $incoming_state = $this->_disengage_incoming_firewall();
        $forwarding_rules = $this->_disengage_port_forwarding();

        // Run certbot renew
        //------------------

        $options['validate_exit_code'] = FALSE;
        $shell = new Shell();

        $retval = $shell->execute(
            self::COMMAND_CERTBOT,
            'renew --standalone ' .
            '--max-log-backups ' . $this->max_logs . ' ' .
            '--preferred-challenges http-01 ' .
            '--renew-hook "/sbin/trigger lets_encrypt"',
            TRUE,
            $options
        );

        $message =($retval == 0) ? lang('lets_encrypt_renew_succeeded') : lang('lets_encrypt_renew_failed');
        $logs = $shell->get_output();

        clearos_log('lets_encrypt', $message);

        foreach ($logs as $log)
            clearos_log('lets_encrypt', $log);

        // Manage daemons and firewall on port 80
        //---------------------------------------

        $this->_engage_incoming_firewall($incoming_state);
        $this->_engage_port_forwarding($forwarding_rules);
        $this->_engage_daemons($daemon_states);
    }

    /**
     * Checks to see if a renewal is required.
     *
     * @return boolean TRUE if renewal required
     * @throws Engine_Exception
     */

    public function renew_required()
    {
        clearos_profile(__METHOD__, __LINE__);

        $options['validate_exit_code'] = FALSE;
        $options['env'] = "LANG=en_US";
        $shell = new Shell();

        // FIXME: add max-logs.  Also consider adding it to other certbot commands
        $retval = $shell->execute(
            self::COMMAND_CERTBOT,
            'renew --standalone ' .
            '--preferred-challenges http-01 ',
            TRUE,
            $options
        );

        $logs = $shell->get_output();

        foreach ($logs as $log) {
            // TODO: checking the output for this status is not ideal.
            // If the text changes, this method will fall back to always trying a renewal.
            if (preg_match('/No renewals were attempted/', $log))
                return FALSE;
        }

        return TRUE;
    }

    /**
     * Sets the admin e-mail address.
     *
     * @param string $email admin e-mail address
     *
     * @return void
     * @throws Engine_Exception
     */

    public function set_email($email)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_email($email));

        $this->_set_parameter('email', $email);
    }

    ///////////////////////////////////////////////////////////////////////////////
    // V A L I D A T I O N  M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Validation routine for the admin e-mail address.
     *
     * @param string $email e-mail address
     *
     * @return string error message if e-mail address is invalid
     */

    public function validate_email($email)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (!preg_match("/^([a-zA-Z0-9])+([a-zA-Z0-9\._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/", $email))
            return lang('base_email_address_invalid');
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Disengages daemons.
     *
     * @access private
     * @return string pre-existing daemon states
     * @throws Engine_Exception
     */

    public function _disengage_daemons()
    {
        clearos_profile(__METHOD__, __LINE__);

        $daemon_states = [];

        foreach ($this->daemon_list as $daemon_name) {
            $daemon = new Daemon($daemon_name);
            $daemon_states[$daemon_name] = FALSE;

            if ($daemon->is_installed()) {
                $daemon_states[$daemon_name] = $daemon->get_running_state();
                $daemon->set_running_state(FALSE);
            }
        }

        return $daemon_states;
    }

    /**
     * Disengages incoming firewall.
     *
     * @access private
     * @return string pre-existing incoming firewall state
     * @throws Engine_Exception
     */

    public function _disengage_incoming_firewall()
    {
        clearos_profile(__METHOD__, __LINE__);

        $incoming_state = '';

        if (clearos_load_library('incoming_firewall/Incoming') && clearos_load_library('firewall/Firewall')) {
            $firewall = new  \clearos\apps\incoming_firewall\Incoming();

            $incoming_state = $firewall->check_port('TCP', 80);

            if ($incoming_state == \clearos\apps\firewall\Firewall::CONSTANT_NOT_CONFIGURED) {
                $firewall->add_allow_port('lets_encrypt80', 'TCP', 80);
                sleep(5);
            } else if ($incoming_state == \clearos\apps\firewall\Firewall::CONSTANT_DISABLED) {
                $firewall->set_allow_port_state(TRUE, 'TCP', 80);
                sleep(5);
            }
        }

        return $incoming_state;
    }

    /**
     * Disengages port forwarding firewall.
     *
     * @access private
     * @return array pre-existing port forward state
     * @throws Engine_Exception
     */

    public function _disengage_port_forwarding()
    {
        clearos_profile(__METHOD__, __LINE__);

        $forwarding_rules = [];

        if (clearos_load_library('port_forwarding/Port_Forwarding')) {
            $forwarding = new \clearos\apps\port_forwarding\Port_Forwarding();

            $rules = $forwarding->get_ports();

            foreach ($rules as $rule) {
                if (($rule['from_port'] == 80) && $rule['enabled'])
                    $forwarding_rules[] = $rule;
            }

            foreach ($forwarding_rules as $rule)
                $forwarding->set_port_state(FALSE, $rule['protocol_name'], $rule['from_port'], $rule['to_port'], $rule['to_ip']);

            if (!empty($forwarding_rules))
                sleep(10);
        }

        return $forwarding_rules;
    }

    /**
     * Engages daemons
     *
     * @param array $states daemon states
     *
     * @access private
     * @return void
     * @throws Engine_Exception
     */

    public function _engage_daemons($states)
    {
        clearos_profile(__METHOD__, __LINE__);

        foreach ($states as $daemon_name => $was_running) {
            $daemon = new Daemon($daemon_name);

            try {
                if ($was_running)
                    $daemon->set_running_state(TRUE);
            } catch (Exception $e) {
                $exit_code = 1;
            }
        }
    }

    /**
     * Engages incoming firewall.
     *
     * @param boolean $state incoming state
     *
     * @access private
     * @return void
     * @throws Engine_Exception
     */

    public function _engage_incoming_firewall($state)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (clearos_load_library('incoming_firewall/Incoming') && clearos_load_library('firewall/Firewall')) {
            $firewall = new  \clearos\apps\incoming_firewall\Incoming();

            if ($state == \clearos\apps\firewall\Firewall::CONSTANT_NOT_CONFIGURED)
                $firewall->delete_allow_port('TCP', 80);
            else if ($state == \clearos\apps\firewall\Firewall::CONSTANT_DISABLED)
                $firewall->set_allow_port_state(FALSE, 'TCP', 80);
        }
    }

    /**
     * Engages port forwarding firewall.
     *
     * @param array $rules port forwarding rules
     *
     * @access private
     * @return void
     * @throws Engine_Exception
     */

    public function _engage_port_forwarding($rules)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (clearos_load_library('port_forwarding/Port_Forwarding')) {
            $forwarding = new \clearos\apps\port_forwarding\Port_Forwarding();

            foreach ($rules as $rule)
                $forwarding->set_port_state(TRUE, $rule['protocol_name'], $rule['from_port'], $rule['to_port'], $rule['to_ip']);
        }
    }

    /**
     * Loads configuration files.
     *
     * @access private
     * @return void
     * @throws Engine_Exception
     */

    protected function _load_config()
    {
        clearos_profile(__METHOD__, __LINE__);

        try {
            $config_file = new Configuration_File(self::FILE_APP_CONFIG);
            $this->config = $config_file->load();
        } catch (File_Not_Found_Exception $e) {
            // Not fatal
        }

        $this->is_loaded = TRUE;
    }

    /**
     * Sets a parameter in the config file.
     *
     * @param string $key   name of the key in the config file
     * @param string $value value for the key
     *
     * @access private
     * @return void
     * @throws Engine_Exception
     */

    protected function _set_parameter($key, $value)
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->is_loaded = FALSE;

        $file = new File(self::FILE_APP_CONFIG);

        if (! $file->exists())
            $file->create("root", "root", "0644");

        $match = $file->replace_lines("/^$key\s*=\s*/", "$key = $value\n");

        if (!$match)
            $file->add_lines("$key = $value\n");
    }
}
