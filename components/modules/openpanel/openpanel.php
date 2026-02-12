<?php
/**
 * OpenPanel module for Blesta 5.12.x
 *
 * Provisions and manages OpenPanel accounts via the OpenAdmin API.
 */
class Openpanel extends Module
{
    const VERSION = '1.0.0';

    /**
     * Module row fields that are stored in module_row_meta.
     *
     * @var array
     */
    private $meta_fields = [
        'server_name',
        'hostname',
        'port',
        'use_ssl',
        'verify_ssl',
        'admin_username',
        'admin_password'
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        Loader::load(dirname(__FILE__) . DS . 'apis' . DS . 'openpanel_api.php');
        Language::loadLang('openpanel', null, dirname(__FILE__) . DS . 'language' . DS);

        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        Loader::loadComponents($this, ['Input']);
        Loader::loadHelpers($this, ['Html']);
    }

    /**
     * Install
     */
    public function install()
    {
        if (!extension_loaded('curl')) {
            $this->Input->setErrors([
                'curl' => [
                    'required' => Language::_('Openpanel.!error.curl', true)
                ]
            ]);
        }
    }

    /**
     * Upgrade
     */
    public function upgrade($current_version)
    {
        // No upgrade routines yet
    }

    /**
     * Uninstall
     */
    public function uninstall($module_id, $last_instance)
    {
        // Nothing to clean up
    }

    public function getName()
    {
        return Language::_('Openpanel.name', true);
    }

    public function getVersion()
    {
        return self::VERSION;
    }

    public function getAuthors()
    {
        return [
            [
                'name' => 'Equitia',
                'url' => 'https://equitia.net'
            ]
        ];
    }

    /**
     * Returns the relative path to the module logo.
     */
    public function getLogo()
    {
        return 'views/default/images/logo.svg';
    }

    public function moduleRowName()
    {
        return Language::_('Openpanel.module_row', true);
    }

    public function moduleRowNamePlural()
    {
        return Language::_('Openpanel.module_row_plural', true);
    }

    public function moduleGroupName()
    {
        return Language::_('Openpanel.module_group', true);
    }

    public function getServiceName($service)
    {
        $fields = $this->serviceFieldsToObject($service->fields);

        return $fields->openpanel_username ?? null;
    }

    public function getPackageServiceName($package, array $vars = null)
    {
        return $vars['openpanel_username'] ?? null;
    }

    /**
     * Manage module rows
     */
    public function manageModule($module, array &$vars)
    {
        $this->view = new View('manage', 'default');
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'openpanel' . DS);
        $this->view->base_uri = $this->base_uri;

        Loader::loadHelpers($this, ['Html', 'Form', 'Widget']);

        if (!isset($this->ModuleManager)) {
            Loader::loadModels($this, ['ModuleManager']);
        }

        // Prefer rows already loaded into the module record; otherwise fetch fresh
        $module_rows = isset($module->rows) && is_array($module->rows) ? $module->rows : [];
        if (empty($module_rows)) {
            $module_rows = (array)$this->ModuleManager->getRows($module->id);
        }

        $this->view->set('module', $module);
        $this->view->set('module_rows', $module_rows);
        $this->view->set('base_uri', $this->base_uri);

        return $this->view->fetch();
    }

    /**
     * Add module row form
     */
    public function manageAddRow(array &$vars)
    {
        $this->view = new View('add_row', 'default');
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'openpanel' . DS);
        $this->view->base_uri = $this->base_uri;

        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        if (empty($vars)) {
            $vars = [
                'port' => 2087,
                'use_ssl' => '1',
                'verify_ssl' => '1'
            ];
        }

        $this->view->set('vars', (object)$vars);

        return $this->view->fetch();
    }

    /**
     * Edit module row form
     */
    public function manageEditRow($module_row, array &$vars)
    {
        $this->view = new View('edit_row', 'default');
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'openpanel' . DS);
        $this->view->base_uri = $this->base_uri;

        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        if (empty($vars)) {
            $vars = $module_row->meta;
        }

        $this->view->set('vars', (object)$vars);

        return $this->view->fetch();
    }

    /**
     * Add module row
     */
    public function addModuleRow(array &$vars)
    {
        $this->setRowRules($vars);

        if (!$this->Input->validates($vars)) {
            return;
        }

        return $this->buildModuleRowMeta($vars);
    }

    /**
     * Edit module row
     */
    public function editModuleRow($module_row, array &$vars)
    {
        $this->setRowRules($vars);

        if (!$this->Input->validates($vars)) {
            return;
        }

        return $this->buildModuleRowMeta($vars);
    }

    public function deleteModuleRow($module_row)
    {
        // No remote cleanup required; letting Blesta remove the row.
        return null;
    }

    /**
     * Select module row for provisioning
     */
    public function selectModuleRow($module_group_id)
    {
        if (!isset($this->ModuleManager)) {
            Loader::loadModels($this, ['ModuleManager']);
        }

        if (($group = $this->ModuleManager->getGroup($module_group_id))) {
            switch ($group->add_order) {
                default:
                case 'first':
                    foreach ($group->rows as $row) {
                        return $row->id;
                    }
                    break;
            }
        }

        return 0;
    }

    /**
     * Package fields (plan selection)
     */
    public function getPackageFields($vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        // Normalise to object for safe property access
        if (!is_object($vars)) {
            $vars = (object)(array)$vars;
        }

        $fields = new ModuleFields();

        $module_row = $this->getSelectedModuleRow($vars);

        $plans = [];
        $api_error = null;
        if ($module_row) {
            try {
                $plans = $this->getPlans($module_row);
            } catch (Exception $e) {
                $api_error = $e->getMessage();
                $this->Input->setErrors(['api' => ['response' => $api_error]]);
            }
        }

        if (!$module_row) {
            $label = $fields->label(Language::_('Openpanel.manage.no_results', true), 'openpanel_no_rows');
            $label->attach(
                $fields->fieldText(
                    'openpanel_no_rows',
                    Language::_('Openpanel.manage.no_results', true),
                    ['id' => 'openpanel_no_rows', 'readonly' => 'readonly']
                )
            );
            return $fields;
        }

        $plan_options = [];
        if (!empty($plans)) {
            foreach ($plans as $plan) {
                $plan_options[$plan['name']] = $plan['name'] . (isset($plan['id']) ? ' (ID ' . $plan['id'] . ')' : '');
            }
        } else {
            $msg = Language::_('Openpanel.package_fields.no_plans', true);
            if ($api_error) {
                // If the error message is too long, truncate it
                $display_error = substr($api_error, 0, 80);
                if (strlen($api_error) > 80) {
                    $display_error .= '...';
                }
                $msg .= ' (Error: ' . $display_error . ')';
            }
            $plan_options[''] = $msg;
        }

        $label = $fields->label(Language::_('Openpanel.package_fields.plan_name', true), 'openpanel_plan');
        $label->attach(
            $fields->fieldSelect(
                'meta[plan_name]',
                $plan_options,
                isset($vars->meta['plan_name']) ? $vars->meta['plan_name'] : null,
                ['id' => 'openpanel_plan']
            )
        );
        $fields->setField($label);

        return $fields;
    }

    /**
     * Save package meta
     */
    public function addPackage(array $vars = null)
    {
        $rules = [
            'meta[plan_name]' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Openpanel.!error.meta[plan_name].empty', true)
                ]
            ]
        ];

        $this->Input->setRules($rules);

        $meta = [];
        if ($this->Input->validates($vars)) {
            foreach ((array)($vars['meta'] ?? []) as $key => $value) {
                $meta[] = [
                    'key' => $key,
                    'value' => $value,
                    'encrypted' => 0
                ];
            }
        }

        return $meta;
    }

    /**
     * Update package meta
     */
    public function editPackage($package, array $vars = null)
    {
        return $this->addPackage($vars);
    }

    public function deletePackage($package)
    {
        return null;
    }

    /**
     * Admin-side service fields
     */
    public function getAdminAddFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();

        $username = $this->Html->ifSet($vars->openpanel_username, $this->generateUsername($vars));
        $password = $this->Html->ifSet($vars->openpanel_password, $this->generatePassword());
        $domain = $this->Html->ifSet($vars->openpanel_domain, $vars->domain ?? '');

        // Domain
        $label = $fields->label(Language::_('Openpanel.service_fields.domain', true), 'openpanel_domain');
        $label->attach($fields->fieldText('openpanel_domain', $domain, ['id' => 'openpanel_domain']));
        $fields->setField($label);

        // Username
        $label = $fields->label(Language::_('Openpanel.service_fields.username', true), 'openpanel_username');
        $label->attach($fields->fieldText('openpanel_username', $username, ['id' => 'openpanel_username']));
        $fields->setField($label);

        // Password
        $label = $fields->label(Language::_('Openpanel.service_fields.password', true), 'openpanel_password');
        $label->attach(
            $fields->fieldPassword('openpanel_password', ['id' => 'openpanel_password', 'value' => $password])
        );
        $fields->setField($label);

        return $fields;
    }

    /**
     * Client-side service fields
     */
    public function getClientAddFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();

        // Domain
        $domain = $this->Html->ifSet($vars->openpanel_domain, $vars->domain ?? '');
        $label = $fields->label(Language::_('Openpanel.service_fields.domain', true), 'openpanel_domain');

        $tooltip = $fields->tooltip(Language::_('Openpanel.service_fields.domain_tooltip', true));
        $label->attach($tooltip);

        $label->attach($fields->fieldText('openpanel_domain', $domain, ['id' => 'openpanel_domain']));
        $fields->setField($label);

        return $fields;
    }

    /**
     * Admin edit service fields
     */
    public function getAdminEditFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        if (!is_object($vars)) {
            $vars = (object)(array)$vars;
        }

        $fields = new ModuleFields();
        $service_obj = $vars->service ?? null;
        $service_fields = $this->serviceFieldsToObject(
            $service_obj && isset($service_obj->fields) ? $service_obj->fields : []
        );

        $label = $fields->label(Language::_('Openpanel.service_fields.username', true), 'openpanel_username');
        $label->attach(
            $fields->fieldText(
                'openpanel_username',
                $this->Html->ifSet($vars->openpanel_username, $service_fields->openpanel_username ?? ''),
                ['id' => 'openpanel_username', 'readonly' => 'readonly']
            )
        );
        $fields->setField($label);

        $label = $fields->label(Language::_('Openpanel.service_fields.password', true), 'openpanel_password');
        $label->attach($fields->fieldPassword('openpanel_password', ['id' => 'openpanel_password']));
        $fields->setField($label);

        return $fields;
    }

    /**
     * Validates service input
     */
    public function validateService($package, array $vars = null)
    {
        $rules = [
            'openpanel_username' => [
                'format' => [
                    'rule' => [[$this, 'validateUsername']],
                    'message' => Language::_('Openpanel.!error.openpanel_username.format', true)
                ]
            ],
            'openpanel_domain' => [
                'format' => [
                    'rule' => [[$this, 'validateDomain']],
                    'message' => Language::_('Openpanel.!error.openpanel_domain.format', true)
                ],
                'test' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Openpanel.!error.openpanel_domain.test', true)
                ]
            ]
        ];

        // Password is not required if auto-generated, but if provided it must be valid?
        // Actually, if it's empty, we generate it. So we only validate if it's NOT empty?
        // Or if it's required by admin. For now let's keep it simple: required if not edit.
        // BUT, we changed client side to be optional. So we need to unset the rule if empty and let generator handle it?
        // No, generator runs in addService. Validation runs BEFORE addService.
        // If validation fails, addService isn't called.
        // So we need to be careful.

        // If username/password are empty on client side, they will be generated later.
        // We should only validate them if they are NOT empty.

        if (!empty($vars['openpanel_username'])) {
             // Rules already set above
        } else {
             unset($rules['openpanel_username']);
        }

        if (!empty($vars['openpanel_password'])) {
             // Validate format if needed
        } elseif (!isset($vars['is_edit']) || $vars['is_edit'] !== '1') {
             // If creating new service and password is empty, it's fine (auto-gen).
             // But if we wanted to enforce custom password? No, optional is better.
             // Original code enforced it. Let's remove enforcement if it's optional.
        }

        $this->Input->setRules($rules);

        return $this->Input->validates($vars);
    }

    /**
     * Provision a new service
     */
    public function addService(
        $package,
        array $vars = null,
        $parent_package = null,
        $parent_service = null,
        $status = 'pending'
    ) {
        if (!$this->validateService($package, $vars)) {
            return;
        }

        $row = $this->getModuleRow();
        if (!$row && isset($package->module_group)) {
            $rows = $this->getModuleRows($package->module_group);
            if (isset($rows[0])) {
                $row = $rows[0];
            }
        }

        if (!$row) {
            $this->Input->setErrors(['module_row' => ['missing' => 'No OpenPanel server is configured.']]);

            return;
        }

        $username = !empty($vars['openpanel_username']) ? $vars['openpanel_username'] : $this->generateUsername($vars);
        $password = !empty($vars['openpanel_password']) ? $vars['openpanel_password'] : $this->generatePassword();
        $domain = $vars['openpanel_domain'] ?? null;
        $plan_name = $package->meta->plan_name ?? null;
        $client_email = $this->getClientEmail($vars['client_id'] ?? null);

        if (isset($vars['use_module']) && $vars['use_module'] === 'true' && $status == 'active') {
            // 1. Check if user exists (Idempotency)
            $user_exists = false;
            try {
                $this->apiRequest($row, 'GET', 'users/' . $username);
                $user_exists = true;
            } catch (Exception $e) {
                // User does not exist (404 likely), proceed to create
            }

            // 2. Create the User (if not exists)
            if (!$user_exists) {
                try {
                    $this->apiRequest(
                        $row,
                        'POST',
                        'users',
                        [
                            'username' => $username,
                            'password' => $password,
                            'email' => $client_email,
                            'plan_name' => $plan_name
                        ]
                    );
                } catch (Exception $e) {
                    $this->Input->setErrors(['api' => ['response' => $e->getMessage()]]);
                    return;
                }
            }
        }

        return [
            ['key' => 'openpanel_username', 'value' => $username, 'encrypted' => 0],
            ['key' => 'openpanel_password', 'value' => $password, 'encrypted' => 1],
            ['key' => 'openpanel_domain', 'value' => $domain, 'encrypted' => 0],
            ['key' => 'plan_name', 'value' => $plan_name, 'encrypted' => 0]
        ];
    }

    /**
     * Update a service
     */
    public function editService(
        $package,
        $service,
        array $vars = [],
        $parent_package = null,
        $parent_service = null
    ) {
        $service_fields = $this->serviceFieldsToObject($service->fields);

        $username = $service_fields->openpanel_username ?? null;
        $password = $vars['openpanel_password'] ?? null;

        if (!$username) {
            $this->Input->setErrors([
                'service' => ['username' => Language::_('Openpanel.!error.openpanel_username.format', true)]
            ]);

            return;
        }

        if (isset($vars['use_module']) && $vars['use_module'] === 'true' && !empty($password)) {
            $row = $this->getModuleRow($service->module_row_id ?? null);
            if (!$row) {
                $row = $this->getModuleRow();
            }

            try {
                $this->apiRequest(
                    $row,
                    'PATCH',
                    'users/' . $username,
                    ['password' => $password]
                );
            } catch (Exception $e) {
                $this->Input->setErrors(['api' => ['response' => $e->getMessage()]]);

                return;
            }
        }

        $fields = [
            ['key' => 'openpanel_username', 'value' => $username, 'encrypted' => 0],
            ['key' => 'plan_name', 'value' => $service_fields->plan_name ?? null, 'encrypted' => 0]
        ];

        if (!empty($password)) {
            $fields[] = ['key' => 'openpanel_password', 'value' => $password, 'encrypted' => 1];
        }

        return $fields;
    }

    /**
     * Cancel (terminate) a service
     */
    public function cancelService($package, $service, $parent_package = null, $parent_service = null)
    {
        $fields = $this->serviceFieldsToObject($service->fields);
        $username = $fields->openpanel_username ?? null;

        if (!$username) {
            return;
        }

        $row = $this->getModuleRow($service->module_row_id ?? null);
        if (!$row) {
            $row = $this->getModuleRow();
        }

        try {
            $this->apiRequest($row, 'DELETE', 'users/' . $username);
        } catch (Exception $e) {
            $this->Input->setErrors(['api' => ['response' => $e->getMessage()]]);
        }

        return null;
    }

    /**
     * Suspend a service
     */
    public function suspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        return $this->setServiceStatus($service, 'suspend');
    }

    /**
     * Unsuspend a service
     */
    public function unsuspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        return $this->setServiceStatus($service, 'unsuspend');
    }

    /**
     * Renew service (no-op)
     */
    public function renewService($package, $service, $parent_package = null, $parent_service = null)
    {
        return null;
    }

    /**
     * Change package (plan)
     */
    public function changeServicePackage(
        $package_from,
        $package_to,
        $service,
        $parent_package = null,
        $parent_service = null
    ) {
        $fields = $this->serviceFieldsToObject($service->fields);
        $username = $fields->openpanel_username ?? null;
        $plan_name = $package_to->meta->plan_name ?? null;

        if (!$username || !$plan_name) {
            return;
        }

        $row = $this->getModuleRow($service->module_row_id ?? null);
        if (!$row) {
            $row = $this->getModuleRow();
        }

        try {
            $this->apiRequest(
                $row,
                'PUT',
                'users/' . $username,
                ['plan_name' => $plan_name]
            );
        } catch (Exception $e) {
            $this->Input->setErrors(['api' => ['response' => $e->getMessage()]]);

            return;
        }

        return [
            ['key' => 'openpanel_username', 'value' => $username, 'encrypted' => 0],
            ['key' => 'plan_name', 'value' => $plan_name, 'encrypted' => 0]
        ];
    }

    /**
     * Admin service info
     */
    public function getAdminServiceInfo($service, $package)
    {
        $row = $this->getModuleRow($service->module_row_id ?? null);
        $fields = $this->serviceFieldsToObject($service->fields);

        $login_link = null;
        if ($row && isset($fields->openpanel_username)) {
            try {
                $login_link = $this->getLoginLink($row, $fields->openpanel_username);
            } catch (Exception $e) {
                // Ignore for admin view
            }
        }

        $this->view = new View('admin_service_info', 'default');
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'openpanel' . DS);

        Loader::loadHelpers($this, ['Html']);

        $this->view->set('service_fields', $fields);
        $this->view->set('package', $package);
        $this->view->set('login_link', $login_link);

        return $this->view->fetch();
    }

    /**
     * Client service info
     */
    public function getClientServiceInfo($service, $package)
    {
        $row = $this->getModuleRow($service->module_row_id ?? null);
        $fields = $this->serviceFieldsToObject($service->fields);

        $login_link = null;
        if ($row && isset($fields->openpanel_username)) {
            try {
                $login_link = $this->getLoginLink($row, $fields->openpanel_username);
            } catch (Exception $e) {
                // Ignore for client view
            }
        }

        $this->view = new View('client_service_info', 'default');
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'openpanel' . DS);

        Loader::loadHelpers($this, ['Html']);

        $this->view->set('service_fields', $fields);
        $this->view->set('package', $package);
        $this->view->set('login_link', $login_link);

        return $this->view->fetch();
    }

    /**
     * Email tags
     */
    public function getEmailTags()
    {
        return [
            'module' => $this->meta_fields,
            'package' => ['plan_name'],
            'service' => ['openpanel_username', 'openpanel_password', 'plan_name', 'openpanel_login_url']
        ];
    }

    /* ===== Helpers ===== */

    private function setRowRules(array $vars)
    {
        $rules = [
            'server_name' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Openpanel.!error.server_name.empty', true)
                ]
            ],
            'hostname' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Openpanel.!error.hostname.empty', true)
                ],
                'format' => [
                    'rule' => [[$this, 'validateHostname']],
                    'message' => Language::_('Openpanel.!error.hostname.format', true)
                ]
            ],
            'port' => [
                'format' => [
                    'rule' => [[$this, 'validatePort']],
                    'message' => Language::_('Openpanel.!error.port.format', true)
                ]
            ],
            'admin_username' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Openpanel.!error.admin_username.empty', true)
                ]
            ],
            'admin_password' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Openpanel.!error.admin_password.empty', true)
                ]
            ]
        ];

        $this->Input->setRules($rules);
    }

    private function buildModuleRowMeta(array $vars)
    {
        $meta = [];

        foreach ($this->meta_fields as $field) {
            if (!isset($vars[$field])) {
                continue;
            }

            $meta[] = [
                'key' => $field,
                'value' => $vars[$field],
                'encrypted' => in_array($field, ['admin_password'], true) ? 1 : 0
            ];
        }

        return $meta;
    }

    public function validateHostname($hostname)
    {
        // IP address
        if (filter_var($hostname, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Domain name
        return (bool)preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $hostname);
    }

    public function validateUsername($username)
    {
        return (bool)preg_match('/^[a-zA-Z0-9._-]{1,32}$/', (string)$username);
    }

    public function validatePort($port)
    {
        return is_numeric($port) && (int)$port >= 1 && (int)$port <= 65535;
    }

    public function validateDomain($domain)
    {
        if (empty($domain)) {
            return true; // Let 'isEmpty' rule handle requiredness if needed, or if optional
        }
        return (bool)preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain);
    }

    private function generateUsername($vars = null)
    {
        // Vars passed as array in some cases, object in others
        $vars = (array)$vars;
        $base = 'user';
        $domain_found = false;

        // 1. Try Domain Name (Best for hosting accounts)
        // e.g. "example.com" -> "example"
        $domain = $vars['openpanel_domain'] ?? ($vars['domain'] ?? null);
        if (!empty($domain)) {
            // Remove protocol if present
            $domain = preg_replace('#^https?://#', '', $domain);
            // Get first part of domain
            $parts = explode('.', $domain);
            if (!empty($parts[0])) {
                $base = $parts[0];
                $domain_found = true;
            }
        }

        // 2. Fallback to Client Name / Company if no domain
        if (!$domain_found) {
            $client = null;
            if (!isset($this->Clients)) {
                Loader::loadModels($this, ['Clients']);
            }

            if (!empty($vars['client_id'])) {
                $client = $this->Clients->get($vars['client_id']);
            } elseif (!empty($vars['user_id'])) {
                $client = $this->Clients->getByUserId($vars['user_id']);
            } elseif (!empty($vars['first_name']) && !empty($vars['last_name'])) {
                $base = substr($vars['first_name'], 0, 1) . $vars['last_name'];
            }

            if ($client) {
                if (!empty($client->company)) {
                    $base = $client->company;
                } elseif (!empty($client->first_name) && !empty($client->last_name)) {
                    $base = substr($client->first_name, 0, 1) . $client->last_name;
                }
            }
        }

        // Sanitize: remove non-alphanumeric chars
        $base = preg_replace('/[^a-zA-Z0-9]/', '', $base);
        $base = strtolower($base);

        // Ensure it starts with a letter (OpenPanel/Linux users usually must)
        if (!preg_match('/^[a-z]/', $base)) {
            $base = 'u' . $base;
        }

        // Truncate
        // If domain based, allow up to 16 chars and NO suffix (unless collision handling needed, but usually fine)
        // If client/random based, truncate shorter and add suffix.
        if ($domain_found) {
            $base = substr($base, 0, 16);
            return $base;
        } else {
            $base = substr($base, 0, 10);
            $suffix = bin2hex(random_bytes(2));
            return $base . $suffix;
        }
    }

    private function generatePassword()
    {
        return bin2hex(random_bytes(10));
    }

    private function getClientEmail($client_id = null)
    {
        if ($client_id && !isset($this->Clients)) {
            Loader::loadModels($this, ['Clients']);
        }

        if ($client_id && ($client = $this->Clients->get($client_id))) {
            return $client->email;
        }

        return null;
    }

    private function getApi($module_row)
    {
        if (!$module_row) {
            throw new Exception('Module row not found.');
        }

        $meta = $module_row->meta;

        return new OpenpanelApi(
            $meta->hostname ?? '',
            $meta->admin_username ?? '',
            $meta->admin_password ?? '',
            ($meta->use_ssl ?? '1') === '1' || $meta->use_ssl === 'true',
            $meta->port ?? 2087,
            ($meta->verify_ssl ?? '1') === '1' || $meta->verify_ssl === 'true'
        );
    }

    private function apiRequest($module_row, $method, $path, array $data = null)
    {
        $api = $this->getApi($module_row);
        $url = $api->getBaseUrl() . '/api/' . ltrim($path, '/');

        $this->log($url, $this->mask($data), 'input', true);

        $response = $api->call($method, $path, $data);

        $success = $this->isSuccessfulResponse($response);
        $this->log($url, $this->stringifyResponse($response), 'output', $success);

        if (!$success) {
            throw new Exception($this->getErrorMessage($response));
        }

        return $response['body'];
    }

    private function getPlans($module_row)
    {
        $response = $this->apiRequest($module_row, 'GET', 'plans');

        if (!isset($response['plans']) || !is_array($response['plans'])) {
            $debug = is_array($response) ? json_encode($response) : $response;
            throw new Exception("Invalid response from OpenPanel API (plans missing): " . substr($debug, 0, 255));
        }

        return $response['plans'];
    }

    private function getLoginLink($module_row, $username)
    {
        $response = $this->apiRequest($module_row, 'CONNECT', 'users/' . $username);

        return $response['link'] ?? null;
    }

    private function setServiceStatus($service, $action)
    {
        $fields = $this->serviceFieldsToObject($service->fields);
        $username = $fields->openpanel_username ?? null;

        if (!$username) {
            return;
        }

        $row = $this->getModuleRow($service->module_row_id ?? null);
        if (!$row) {
            $row = $this->getModuleRow();
        }

        // Idempotency: Check status before applying action if possible
        // Ideally we would check 'GET /api/users/<USERNAME>' and see if 'suspended' is true/false.
        // But the 'suspend' action in OpenPanel is usually idempotent itself (suspending a suspended user is a no-op).
        // If it throws an error for already-suspended users, we should catch it.

        try {
            $this->apiRequest($row, 'PATCH', 'users/' . $username, ['action' => $action]);
        } catch (Exception $e) {
            // Ignore "Already suspended" or "Not suspended" errors if the API throws them
            $msg = $e->getMessage();
            if (stripos($msg, 'already') !== false) {
                return null;
            }
            $this->Input->setErrors(['api' => ['response' => $e->getMessage()]]);
        }

        return null;
    }

    private function getSelectedModuleRow($vars)
    {
        if (!is_object($vars)) {
            $vars = (object)(array)$vars;
        }

        $module_row = null;

        if (isset($vars->module_group) && $vars->module_group == '') {
            if (isset($vars->module_row) && $vars->module_row > 0) {
                $module_row = $this->getModuleRow($vars->module_row);
            } else {
                $rows = $this->getModuleRows();
                if (isset($rows[0])) {
                    $module_row = $rows[0];
                }
                unset($rows);
            }
        } else {
            $rows = $this->getModuleRows($vars->module_group ?? null);
            if (isset($rows[0])) {
                $module_row = $rows[0];
            }
            unset($rows);
        }

        return $module_row;
    }

    private function isSuccessfulResponse(array $response)
    {
        if (isset($response['body']['error'])) {
            return false;
        }

        if (isset($response['body']['success'])) {
            return (bool)$response['body']['success'];
        }

        return $response['status'] >= 200 && $response['status'] < 300;
    }

    private function getErrorMessage(array $response)
    {
        if (isset($response['body']['message'])) {
            return $response['body']['message'];
        }

        if (isset($response['body']['error'])) {
            return $response['body']['error'];
        }

        return Language::_('Openpanel.!error.api.internal', true);
    }

    private function mask($data)
    {
        if (!$data) {
            return '';
        }

        $masked = $data;

        if (is_array($masked)) {
            foreach (['password', 'admin_password'] as $field) {
                if (isset($masked[$field])) {
                    $masked[$field] = '***';
                }
            }
        }

        return json_encode($masked);
    }

    private function stringifyResponse(array $response)
    {
        return json_encode(
            [
                'status' => $response['status'] ?? null,
                'body' => $response['body'] ?? null
            ]
        );
    }
}
