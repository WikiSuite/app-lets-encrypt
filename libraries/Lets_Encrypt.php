<?php

/**
 * Let's Encrypt class.
 *
 * @category   apps
 * @package    lets-encrypt
 * @subpackage controllers
 * @author     eGloo <developer@egloo.ca>
 * @copyright  2017 Marc Laporte
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
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
use \clearos\apps\base\File as File;
use \clearos\apps\base\Folder as Folder;
use \clearos\apps\base\Shell as Shell;
use \clearos\apps\base\Software as Software;
use \clearos\apps\certificate_manager\SSL as SSL;
use \clearos\apps\network\Network_Utils as Network_Utils;

clearos_load_library('base/Configuration_File');
clearos_load_library('base/File');
clearos_load_library('base/Folder');
clearos_load_library('base/Shell');
clearos_load_library('base/Software');
clearos_load_library('certificate_manager/SSL');
clearos_load_library('network/Network_Utils');

// Exceptions
//-----------

use \clearos\apps\base\File_Not_Found_Exception as File_Not_Found_Exception;
use \clearos\apps\base\Validation_Exception as Validation_Exception;

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
 * @copyright  2017 Marc Laporte
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       https://github.com/eglooca/app-lets-encrypt
 */

class Lets_Encrypt extends Software
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const APP_CONFIG = '/etc/clearos/lets_encrypt.conf';
    const PATH_CERTIFICATES = '/etc/letsencrypt/live';
    const COMMAND_CERTBOT = '/usr/bin/certbot';
    const FILE_CERT = 'cert.pem';
    const FILE_LOG_PREFIX = 'lets-encrypt-';

    ///////////////////////////////////////////////////////////////////////////////
    // V A R I A B L E S
    ///////////////////////////////////////////////////////////////////////////////

    protected $is_loaded = FALSE;
    protected $config = array();

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
     * Deletes a certificate.
     *
     * @param string $email   e-mail address to register for updates
     * @param string $domain  primary domain
     * @param array  $domains list of other domains
     *
     * @return void
     */

    public function add($email, $domain, $domains)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_email($email));
        Validation_Exception::is_valid($this->validate_domain($domain));
        if (!empty($domains))
            Validation_Exception::is_valid($this->validate_domains($domains));

        // Delete old log output file
        //---------------------------

        $log_basename = self::FILE_LOG_PREFIX . $domain . '.log';

        $log = new File(CLEAROS_TEMP_DIR . '/' . $log_basename, TRUE);
        if ($log->exists())
            $log->delete();

        // Generate domain list
        //---------------------

        $raw_domains = trim($domain) . ' ' . trim($domains);
        $domain_param = preg_replace('/\s+/', ',', trim($raw_domains));

        // Run certbot
        //------------

        $options['log'] = $log_basename;
        $options['validate_exit_code'] = FALSE;
        $options['env'] = 'LANG=en_US';

        $shell = new Shell();

        // For testing
        $test_cert = '';
        // $test_cert = '--test-cert';

        $exit_code = $shell->execute(
            self::COMMAND_CERTBOT,
            $test_cert . ' --apache --agree-tos -n -m ' . $email . ' -d "' . $domain_param . '" certonly',
            TRUE,
            $options
        );

        if ($exit_code === 0)
            $retval = '';
        else
            $retval = $this->get_log($domain);

        return $retval;
    }

    /**
     * Deletes a certificate.
     *
     * @param string $name ceritificate name
     *
     * @return void
     */

    public function delete($name)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_certificate_name($name));

        $shell = new Shell();
        $shell->execute(self::COMMAND_CERTBOT, 'delete --cert-name ' . $name, TRUE);
    }

    /**
     * Returns a list of certificates.
     *
     * @return array a list of certificates
     */

    public function get_certificates()
    {
        clearos_profile(__METHOD__, __LINE__);

        $folder = new Folder(self::PATH_CERTIFICATES, TRUE);

        if (!$folder->exists())
            return [];

        $certificate_list = $folder->get_listing();

        $ssl = new SSL();

        $certs = [];

        foreach ($certificate_list as $certificate)
            $certs[$certificate] = $ssl->get_certificate_attributes(self::PATH_CERTIFICATES . '/' . $certificate . '/' . self::FILE_CERT);

        return $certs;
    }

    /**
     * Returns a list of certificates.
     *
     * @return array a list of certificates
     */

    public function get_certificate_files()
    {
        clearos_profile(__METHOD__, __LINE__);

        $folder = new Folder(self::PATH_CERTIFICATES, TRUE);

        if (!$folder->exists())
            return [];

        $certificate_list = $folder->get_listing();

        $cert_files = [];

        foreach ($certificate_list as $certificate) {
            $base_path = self::PATH_CERTIFICATES . '/' . $certificate . '/';
            $cert_files[$certificate]['certificate-filename'] = $base_path . 'cert.pem';
            $cert_files[$certificate]['key-filename'] = $base_path . 'privkey.pem';
            $cert_files[$certificate]['intermediate-filename'] = $base_path . 'chain.pem';
        }

        return $cert_files;
    }

    /**
     * Returns certificate attributes.
     *
     * @param string $certificate certificate basename
     *
     * @return array list of certificate attributes
     * @throws Certificate_Not_Found_Exception, Engine_Exception
     */


    public function get_certificate_attributes($certificate)
    {
        clearos_profile(__METHOD__, __LINE__);

        $ssl = new SSL();

        return $ssl->get_certificate_attributes(self::PATH_CERTIFICATES . '/' . $certificate . '/' . self::FILE_CERT);
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
     * Returns array of log lines.
     *
     * @param string $certificate certificate basename
     *
     * @return array log lines
     * @throws Engine_Exception
     */

    public function get_log($certificate)
    {
        clearos_profile(__METHOD__, __LINE__);

        $log_basename = self::FILE_LOG_PREFIX . $certificate . '.log';

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
     * Validation routine for ceritificates.
     *
     * @param string $name certificate name
     *
     * @return string error message if certificate is invalid
     */

    public function validate_certificate_name($name)
    {
        clearos_profile(__METHOD__, __LINE__);

        $certificates = $this->get_certificates();

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

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

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
            $config_file = new Configuration_File(self::APP_CONFIG);
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

        $file = new File(self::APP_CONFIG);

        if (! $file->exists())
            $file->create("root", "root", "0644");

        $match = $file->replace_lines("/^$key\s*=\s*/", "$key = $value\n");

        if (!$match)
            $file->add_lines("$key = $value\n");
    }
}
