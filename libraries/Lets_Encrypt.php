<?php

/**
 * Let's Encrypt class.
 *
 * @category   apps
 * @package    lets-encrypt
 * @subpackage controllers
 * @author     Marc Laporte
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

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

use \clearos\apps\base\Software as Software;

clearos_load_library('base/Software');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Let's Encrypt class.
 *
 * @category   apps
 * @package    lets-encrypt
 * @subpackage controllers
 * @author     Marc Laporte
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
     * Returns the admin e-mail address.
     *
     * @return string admin e-mail address
     * @throws Engine_Exception
     */

    public function get_email()
    {
        clearos_profile(__METHOD__, __LINE__);

        return 'bob@example.com';
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

    public function validate_validate_email($email)
    {
        clearos_profile(__METHOD__, __LINE__);
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

}
