<?php

/**
 * Let's Encrypt certificates view.
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

$read_only = TRUE;
$form = 'lets_encrypt/certificate';
$buttons = array(
    anchor_custom('/app/lets_encrypt', lang('base_return_to_summary'))
);

if (empty($state))
    $buttons[] = anchor_custom('/app/lets_encrypt/certificate/delete/' . $certificate, lang('base_delete'), 'low');

///////////////////////////////////////////////////////////////////////////////
// Form
///////////////////////////////////////////////////////////////////////////////

if ($is_new)
    echo infobox_highlight(lang('lets_encrypt_certificate_created'), lang('lets_encrypt_certificate_created_help'));

if (!empty($state))
    echo infobox_highlight(lang('certificate_manager_deployed'), lang('certificate_manager_deployed_help'));

echo form_open($form, array('id' => 'certificate_form'));
echo form_header(lang('lets_encrypt_certificate'));

echo field_input('issued', $issued, lang('lets_encrypt_issued'), $read_only);
echo field_input('expires', $expires, lang('lets_encrypt_expires'), $read_only);
echo field_textarea('domains', implode("\n", $domains), lang('lets_encrypt_domains'), $read_only);

echo field_button_set($buttons);

echo form_footer();
echo form_close();

///////////////////////////////////////////////////////////////////////////////
// State
///////////////////////////////////////////////////////////////////////////////

if (!$is_new) {
    $items = array();
    $anchors = array();

    $headers = array(
        lang('certificate_manager_app'),
        lang('base_details'),
    );

    foreach ($state as $certificate) {
        $item['title'] = $certificate['app_description'];
        $item['details'] = array(
            $certificate['app_description'],
            $certificate['app_key'],
        );

        $items[] = $item;
    }

    $options['no_action'] = TRUE;
    $options['empty_table_message'] = lang('certificate_manager_not_in_use');

    echo summary_table(
        lang('certificate_manager_deployed'),
        $anchors,
        $headers,
        $items,
        $options
    );
}

///////////////////////////////////////////////////////////////////////////////
// Details
///////////////////////////////////////////////////////////////////////////////

echo box_open(lang('base_details') . ' - ' . $certificate);
echo box_content_open();
echo "
    <span style='white-space: pre-wrap;'>
    $details
    </span>
";
echo box_content_close();
echo box_close();
