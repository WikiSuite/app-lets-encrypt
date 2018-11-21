<?php

/**
 * Let's Encrypt certificates class.
 *
 * @category   apps
 * @package    lets-encrypt
 * @subpackage libraries
 * @author     eGloo <developer@egloo.ca>
 * @copyright  2017-2018 Marc Laporte
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       https://github.com/WikiSuite/app-lets-encrypt
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

use \clearos\apps\base\Engine as Engine;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Folder as Folder;
use \clearos\apps\base\Shell as Shell;
use \clearos\apps\certificate_manager\SSL as SSL;
use \clearos\apps\lets_encrypt\Lets_Encrypt_Class as Lets_Encrypt_Class;
use \clearos\apps\network\Network_Utils as Network_Utils;

clearos_load_library('base/Engine');
clearos_load_library('base/File');
clearos_load_library('base/Folder');
clearos_load_library('base/Shell');
clearos_load_library('certificate_manager/SSL');
clearos_load_library('lets_encrypt/Lets_Encrypt_Class');
clearos_load_library('network/Network_Utils');

// Exceptions
//-----------

use \Exception as Exception;
use \clearos\apps\base\Not_Found_Exception as Not_Found_Exception;
use \clearos\apps\base\Validation_Exception as Validation_Exception;

clearos_load_library('base/Not_Found_Exception');
clearos_load_library('base/Validation_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Let's Encrypt certificates class.
 *
 * @category   apps
 * @package    lets-encrypt
 * @subpackage controllers
 * @author     eGloo <developer@egloo.ca>
 * @copyright  2017-2018 Marc Laporte
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       https://github.com/WikiSuite/app-lets-encrypt
 */

class Certificates_Class extends Engine
{
    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Let's Encrypt certificates constructor.
     */

    public function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);
    }

    ///////////////////////////////////////////////////////////////////////////////
    // R E S T  M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Creates a Let's Encrypt certificate.
     *
     * @param string $email   e-mail address to register for updates
     * @param string $domain  primary domain
     * @param array  $domains list of other domains
     *
     * @return array error entries if an error occurred.
     * @throws Already_Exists_Exception, Engine_Exception
     */

    public function create($email, $domain, $domains)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Validation
        //-----------

        Validation_Exception::is_valid($this->validate_email($email));
        Validation_Exception::is_valid($this->validate_domain($domain));
        if (!empty($domains))
            Validation_Exception::is_valid($this->validate_domains($domains));

        // Delete old log output file
        //---------------------------

        $log_basename = Lets_Encrypt_Class::FILE_LOG_PREFIX . $domain . '.log';

        $log = new File(CLEAROS_TEMP_DIR . '/' . $log_basename, TRUE);
        if ($log->exists())
            $log->delete();

        // Generate domain list
        //---------------------

        $domains = preg_replace('/,/', ' ', $domains); // Strip commas, re-add below
        $raw_domains = trim($domain) . ' ' . trim($domains);
        $domain_param = preg_replace('/\s+/', ',', trim($raw_domains));

        // Manage daemons and firewall on port 80
        //---------------------------------------

        $lets_encrypt = new Lets_Encrypt_Class();

        $daemon_states = $lets_encrypt->_disengage_daemons();
        $incoming_state = $lets_encrypt->_disengage_incoming_firewall();
        $forwarding_rules = $lets_encrypt->_disengage_port_forwarding();
        
        // Run certbot
        //------------

        try {
            $options['log'] = $log_basename;
            $options['validate_exit_code'] = FALSE;
            $options['env'] = 'LANG=en_US';

            $shell = new Shell();

            $test_cert = '';

            // Devel environments run on port 1501.  Default to test mode.
            if (!empty($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == 1501))
                $test_cert = '--test-cert';

            $exit_code = $shell->execute(
                Lets_Encrypt_Class::COMMAND_CERTBOT,
                $test_cert . ' --standalone --agree-tos -n -m ' . $email . ' -d "' . $domain_param . '" certonly',
                TRUE,
                $options
            );
        } catch (Exception $e) {
            $exit_code = 1;
        }

        // Manage daemons and firewall on port 80
        //---------------------------------------

        $lets_encrypt->_engage_incoming_firewall($incoming_state);
        $lets_encrypt->_engage_port_forwarding($forwarding_rules);
        $lets_encrypt->_engage_daemons($daemon_states);

        // Return
        //-------

        if ($exit_code === 0) {
            $log_entries = [];
        } else {
            $log_entries = $this->get_log($domain);

            foreach ($log_entries as $log) {
                if (preg_match('/Connection refused/', $log)) {
                    array_unshift($log_entries, lang('lets_encrypt_connection_refused_warning'), '', '');
                    return $log_entries;
                }
            }
        }

        return $log_entries;
    }

    /**
     * Deletes certificate.
     *
     * @param string $name ceritificate domain name
     *
     * @return void
     * @throws Not_Found_Exception, Engine_Exception
     */

    public function delete($name)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (!$this->exists($name))
            throw new Not_Found_Exception();

        $shell = new Shell();
        $shell->execute(Lets_Encrypt_Class::COMMAND_CERTBOT, 'delete --cert-name ' . $name, TRUE);
    }

    /**
     * Checks the existence of certificate.
     *
     * @param string $name ceritificate domain name
     *
     * @return boolean TRUE if certificate exists
     * @throws Engine_Exception
     */

    public function exists($name)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_name($name));

        try {
            $attributes = $this->get($name);
        } catch (Not_Found_Exception $e) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Returns certificate information.
     *
     * @param string  $name     certificate domain name
     * @param boolean $detailed return detailed information
     *
     * @return array certificate information
     * @throws Not_Found_Exception, Engine_Exception
     */

    public function get($name, $detailed = TRUE)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_name($name));

        $listing = $this->listing($detailed);

        if (!array_key_exists($name, $listing))
            throw new Not_Found_Exception();

        return $listing[$name];
    }

    /**
     * Returns lists of certificates and details.
     *
     * @param boolean $detailed return full details
     *
     * @return array list of certs with details
     * @throws Engine_Exception
     */

    public function listing($detailed = TRUE)
    {
        clearos_profile(__METHOD__, __LINE__);

        $folder = new Folder(Lets_Encrypt_Class::PATH_CERTIFICATES, TRUE);

        if (!$folder->exists())
            return [];

        $certificate_list = $folder->get_listing();

        $ssl = new SSL();

        $certs = [];

        foreach ($certificate_list as $certificate) {
            $base_path = Lets_Encrypt_Class::PATH_CERTIFICATES . '/' . $certificate . '/';

            $certs[$certificate]['certificate'] = $ssl->get_certificate_attributes($base_path . 'cert.pem', $detailed);
            $certs[$certificate]['certificate']['filename'] = $base_path . 'cert.pem';
            $certs[$certificate]['key']['filename'] = $base_path . 'privkey.pem';
            $certs[$certificate]['intermediate']['filename'] = $base_path . 'chain.pem';
            $certs[$certificate]['fullchain']['filename'] = $base_path . 'fullchain.pem';
        }

        return $certs;
    }

    /**
     * Returns array of log lines.
     *
     * @param string $certificate certificate name
     *
     * @return array log lines
     * @throws Engine_Exception
     */

    public function get_log($name)
    {
        clearos_profile(__METHOD__, __LINE__);

        $log_basename = Lets_Encrypt_Class::FILE_LOG_PREFIX . $name . '.log';

        $log = new File(CLEAROS_TEMP_DIR . '/' . $log_basename);
        if (!$log->exists())
            return [];

        $lines = $log->get_contents_as_array();

        $important = [];
        $important_found = FALSE;

        foreach ($lines as $line) {
            // KLUDGE: trying to extract only the good stuff
            if ($important_found)
                $important[] = $line;

            if (preg_match('/IMPORTANT NOTES:/', $line))
                $important_found = TRUE;
        }

        if (empty($important))
            $important = $lines;

        return $important;
    }


    ///////////////////////////////////////////////////////////////////////////////
    // V A L I D A T I O N  M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Validation routine for ceritificates.
     *
     * @param string $name certificate name
     *
     * @return string error message if certificate is invalid
     */

    public function validate_name($name)
    {
        clearos_profile(__METHOD__, __LINE__);

        $certificates = $this->listing();

        if (! array_key_exists($name, $certificates))
            return lang('certificate_manager_certificate_invalid');
    }

    /**
     * Validation routine for primary domain.
     *
     * @param string $domain primary domain
     *
     * @return string error message if primary domain is invalid
     */

    public function validate_domain($domain)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (!Network_Utils::is_valid_domain($domain))
            return lang('network_domain_invalid');
    }

    /**
     * Validation routine for domain list.
     *
     * @param string $domains domain list
     *
     * @return string error message domain list is invalid
     */

    public function validate_domains($domains)
    {
        clearos_profile(__METHOD__, __LINE__);

        $domains = preg_replace('/,/', ' ', $domains);
        $domain_list = preg_split('/\s+/', $domains);
        $valid = TRUE;

        foreach ($domain_list as $domain) {
            if ($this->validate_domain($domain))
                $valid = FALSE;
        }

        if (!$valid)
            return lang('lets_encrypt_domain_list_invalid');
    }

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
}
