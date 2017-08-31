<?php

/**
 * Let's Encrypt add certificates view.
 *
 * @category   apps
 * @package    lets-encrypt
 * @subpackage views
 * @author     Marc Laporte
 * @copyright  2017 Marc Laporte
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       https://github.com/eglooca/app-lets-encrypt
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
// Load dependencies
///////////////////////////////////////////////////////////////////////////////

$this->lang->load('base');
$this->lang->load('lets_encrypt');

///////////////////////////////////////////////////////////////////////////////
// Form handler
///////////////////////////////////////////////////////////////////////////////

if ($provisioning)
    $read_only = TRUE;
else
    $read_only = FALSE;

///////////////////////////////////////////////////////////////////////////////
// Form
///////////////////////////////////////////////////////////////////////////////

if (!empty($state))
    echo infobox_highlight(lang('certificate_manager_deployed'), lang('certificate_manager_deployed_help'));

echo form_open('lets_encrypt/certificate/add');
echo form_header(lang('lets_encrypt_certificate'));
echo "<input type='hidden' id='lets_encrypt_validated' value='$provisioning'>";

echo field_input('email', $email, lang('base_email_address'), $read_only);
echo field_input('domain', $domain, lang('lets_encrypt_primary_domain'), $read_only);
echo field_textarea('domains', $domains, lang('lets_encrypt_other_domains'), $read_only);

echo field_button_set(
    array(
        form_submit_add('submit'),
        anchor_cancel('/app/lets_encrypt')
    )
);

echo form_footer();
echo form_close();

///////////////////////////////////////////////////////////////////////////////
// Provisioning
///////////////////////////////////////////////////////////////////////////////

echo "<div id='provisioning' style='display:none'>";
echo infobox_highlight(lang('base_status'), loading('normal', 'Requesting certificate...'));
echo "</div>";
