<?php
/**
 * OpenPanel language definitions.
 */
$lang['Openpanel.name'] = 'OpenPanel';
$lang['Openpanel.description'] = 'Provision and manage OpenPanel accounts through the OpenAdmin API.';

$lang['Openpanel.module_row'] = 'OpenPanel Server';
$lang['Openpanel.module_row_plural'] = 'OpenPanel Servers';
$lang['Openpanel.module_group'] = 'Server Group';

$lang['Openpanel.manage.module_rows_title'] = 'Servers';
$lang['Openpanel.manage.add_row'] = 'Add Server';
$lang['Openpanel.manage.edit'] = 'Edit';
$lang['Openpanel.manage.delete'] = 'Delete';
$lang['Openpanel.manage.actions'] = 'Actions';
$lang['Openpanel.manage.save'] = 'Save';
$lang['Openpanel.manage.security'] = 'Security';
$lang['Openpanel.manage.delete_confirm'] = 'Are you sure you want to delete this server?';
$lang['Openpanel.manage.no_results'] = 'No servers configured yet.';

$lang['Openpanel.manage.server_name'] = 'Label';
$lang['Openpanel.manage.hostname'] = 'Hostname / IP';
$lang['Openpanel.manage.port'] = 'API Port';
$lang['Openpanel.manage.use_ssl'] = 'Use HTTPS';
$lang['Openpanel.manage.verify_ssl'] = 'Verify TLS certificate';
$lang['Openpanel.manage.admin_username'] = 'Admin username';
$lang['Openpanel.manage.admin_password'] = 'Admin password';

$lang['Openpanel.package_fields.plan_name'] = 'OpenPanel Plan';
$lang['Openpanel.package_fields.no_plans'] = 'No plans found';
$lang['Openpanel.package_fields.plan_help'] = 'Plans are pulled live from the selected OpenPanel server.';

$lang['Openpanel.service_fields.domain'] = 'Domain';
$lang['Openpanel.service_fields.domain_tooltip'] = 'Enter your registered domain name correctly (e.g., example.com). This will be the main domain for your OpenPanel account.';
$lang['Openpanel.service_fields.username'] = 'Username';
$lang['Openpanel.service_fields.password'] = 'Password';

$lang['Openpanel.service_info.username'] = 'Username';
$lang['Openpanel.service_info.plan'] = 'Plan';
$lang['Openpanel.service_info.login'] = 'Log in to OpenPanel';
$lang['Openpanel.service_info.login_unavailable'] = 'Single-sign-on link is not available at the moment.';

$lang['Openpanel.!error.server_name.empty'] = 'Please enter a label for this server.';
$lang['Openpanel.!error.hostname.empty'] = 'Please enter the server hostname or IP.';
$lang['Openpanel.!error.hostname.format'] = 'Please enter a valid domain name (e.g. panel.example.com) or an IP address.';
$lang['Openpanel.!error.port.format'] = 'API port must be a number between 1 and 65535.';
$lang['Openpanel.!error.admin_username.empty'] = 'Please enter the admin username.';
$lang['Openpanel.!error.admin_password.empty'] = 'Please enter the admin password.';
$lang['Openpanel.!error.meta[plan_name].empty'] = 'Please select an OpenPanel plan.';
$lang['Openpanel.!error.openpanel_username.format'] = 'A username is required and may only include letters, numbers, dots, dashes, or underscores.';
$lang['Openpanel.!error.openpanel_domain.format'] = 'Please enter a valid domain name (e.g., example.com).';
$lang['Openpanel.!error.openpanel_domain.test'] = 'Domain name cannot be blank.';
$lang['Openpanel.!error.openpanel_password.empty'] = 'A password is required for provisioning.';
$lang['Openpanel.!error.api.internal'] = 'The OpenPanel API returned an unexpected response.';
$lang['Openpanel.!error.api.auth'] = 'Authentication with OpenPanel failed. Please verify credentials.';
$lang['Openpanel.!error.curl'] = 'The cURL PHP extension is required to communicate with the OpenPanel API.';
