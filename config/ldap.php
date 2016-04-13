<?php
return array(
    'plugins' => array(
        'adldap' => array(
            'account_suffix'=>  '@local.dev',
            'domain_controllers'=>  array(
                //'192.168.0.1',
                'local.dev'
            ), // Load balancing domain controllers
            'base_dn'   =>  'dc=local,dc=dev',
            'admin_username' => 'dc=admin,dc=local,dc=dev', // This is required for session persistance in the application
            'admin_password' => 'M!klotov10',
        ),
    ),
);
?>