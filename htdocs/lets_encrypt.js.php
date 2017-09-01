<?php

/**
 * Let's Encrypt javascript helper.
 *
 * @category   apps
 * @package    lets-encrypt
 * @subpackage javascript
 * @author     eGloo <developer@egloo.ca>
 * @copyright  2017 Marc Laporte
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       https://github.com/eglooca/app-lets-encrypt
 */

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('base');
clearos_load_language('active_directory');

///////////////////////////////////////////////////////////////////////////////
// J A V A S C R I P T
///////////////////////////////////////////////////////////////////////////////

header('Content-Type:application/x-javascript');
?>

$(document).ready(function() {

    $("#provisioning-form-wrapper").show();

    if ($("#lets_encrypt_validated").val() == 1)
         provisionDomain();

    function provisionDomain() {
        $("#provisioning-form-wrapper").hide();
        $("#provisioning-log-wrapper").hide();
        $("#provisioning-wrapper").show();

        var domain = $("#domain").val();

        $.ajax({
            url: '/app/lets_encrypt/certificate/add_ajax',
            method: 'POST',
            data: 'ci_csrf_token=' + $.cookie('ci_csrf_token') + 
                '&email=' + $("#email").val() +
                '&domain=' + $("#domain").val() +
                '&domains=' + $("#domains").val(),
            success : function(payload) {
                if (payload.length == 0)
                    window.location = '/app/lets_encrypt/certificate/view/' + domain + '/true';
                else
                    showLog(payload);
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                window.setTimeout(getLog, 3000);
            }
        });
    }

    function showLog(payload) {
        $("#provisioning-wrapper").hide();
        $("#provisioning-form-wrapper").show();
        $("#provisioning-log-wrapper").show();

        var lines = '';

        for (inx = 0; inx < payload.length; inx++)
            lines += payload[inx] + '<br>';

        $("#provisioning-log").html(lines);
    }


});

// vim: ts=4 syntax=javascript

