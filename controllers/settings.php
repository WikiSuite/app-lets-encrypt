<?php

/**
 * Let's Encrypt settings controller.
 *
 * @category   apps
 * @package    lets-encrypt
 * @subpackage controllers
 * @author     eGloo <developer@egloo.ca>
 * @copyright  2017-2018 Marc Laporte
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       https://github.com/WikiSuite/app-lets-encrypt
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Let's Encrypt settings controller.
 *
 * @category   apps
 * @package    lets-encrypt
 * @subpackage controllers
 * @author     eGloo <developer@egloo.ca>
 * @copyright  2017-2018 Marc Laporte
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       https://github.com/WikiSuite/app-lets-encrypt
 */

class Settings extends ClearOS_Controller
{
    /**
     * Let's Encrypt settings default controller.
     *
     * @return view
     */

    function index()
    {
        $this->_common('view');
    }

    /**
     * Edit view.
     *
     * @return view
     */

    function edit()
    {
        $this->_common('edit');
    }

    /**
     * View view.
     *
     * @return view
     */

    function view()
    {
        $this->_common('view');
    }

    /**
     * Common view/edit handler.
     *
     * @param string $form_type form type
     *
     * @return view
     */

    function _common($form_type)
    {
        // Load dependencies
        //------------------

        $this->lang->load('lets_encrypt');
        $this->load->library('lets_encrypt/Lets_Encrypt_Class');

        // Set validation rules
        //---------------------

        $this->form_validation->set_policy('email', 'lets_encrypt/Lets_Encrypt_Class', 'validate_email', TRUE);
        $form_ok = $this->form_validation->run();

        // Handle form submit
        //-------------------

        if ($this->input->post('submit') && $form_ok) {
            try {
                $this->lets_encrypt_class->set_email($this->input->post('email'));

                $this->page->set_status_updated();
                redirect('/lets_encrypt/settings');
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        // Load view data
        //---------------

        try {
            $data['form_type'] = $form_type;
            $data['email'] = $this->lets_encrypt_class->get_email();
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load views
        //-----------

        $this->page->view_form('lets_encrypt/settings', $data, lang('base_settings'));
    }
}
