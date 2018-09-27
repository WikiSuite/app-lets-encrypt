<?php

/**
 * Let's Encrypt API controller.
 *
 * @category   apps
 * @package    lets-encrypt
 * @subpackage rest-api
 * @author     eGloo <developer@egloo.ca>
 * @copyright  2018 Marc Laporte
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
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Classes
//--------

use \clearos\apps\lets_encrypt\Lets_Encrypt_Class as Lets_Encrypt_Class;

clearos_load_library('lets_encrypt/Lets_Encrypt_Class');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Let's Encrypt API controller.
 *
 * @category   apps
 * @package    lets-encrypt
 * @subpackage rest-api
 * @author     eGloo <developer@egloo.ca>
 * @copyright  2018 Marc Laporte
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       https://github.com/WikiSuite/app-lets-encrypt
 */

class Lets_Encrypt extends ClearOS_REST_Controller
{
    /**
     * Let's Encrypt overview.
     *
     * @return view
     */

    function index_get()
    {
        $data['capabilities'] = [
            '/api/v1/lets_encrypt' => 'General Let\'s Encrypt API',
            '/api/v1/lets_encrypt/certificates' => 'Certificates API'
        ];

        $this->respond_success($data);
    }
}
