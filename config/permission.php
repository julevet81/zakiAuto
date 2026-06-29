<?php

return [

    'models' => [

        /*
         * When using the "HasPermissions" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your permissions. Of course, it
         * is often just the "Permission" model but you may use whatever you like.
         *
         * The model you want to use as a Permission model needs to implement the
         * `Spatie\Permission\Contracts\Permission` contract.
         */

        'permission' => Spatie\Permission\Models\Permission::class,

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your roles. Of course, it
         * is often just the "Role" model but you may use whatever you like.
         *
         * The model you want to use as a Role model needs to implement the
         * `Spatie\Permission\Contracts\Role` contract.
         */

        'role' => Spatie\Permission\Models\Role::class,

    ],

    'table_names' => [

        /*
         * Replace this with your own table name if you have one. May be necessary
         * if you call your roles "permission_groups" or a similar name.
         */

        'roles' => 'roles',

        /*
         * Replace this with your own table name if you have one. May be necessary
         * if you call your permissions "abilities" or a similar name.
         */

        'permissions' => 'permissions',

        /*
         * Replace this with your own table name if you have one. May be necessary
         * if you call your model_has_permissions table something else.
         */

        'model_has_permissions' => 'model_has_permissions',

        /*
         * Replace this with your own table name if you have one. May be necessary
         * if you call your model_has_roles table something else.
         */

        'model_has_roles' => 'model_has_roles',

        /*
         * Replace this with your own table name if you have one. May be necessary
         * if you call your role_has_permissions table something else.
         */

        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        /*
         * Change this if you want to name the related pivots other than defaults
         */
        'role_pivot_key' => null, // default 'role_id',
        'permission_pivot_key' => null, // default 'permission_id',

        /*
         * Change this if you want to name the related model primary key other than
         * `model_id`.
         *
         * For example, this would be nice if your primary keys are all UUIDs. In
         * that case, name this `model_uuid`.
         */

        'model_morph_key' => 'model_id',

        /*
         * Change this if you want to use the teams feature and your related model's
         * foreign key is other than `team_id`.
         */

        'team_foreign_key' => 'team_id',
    ],

    /*
     * When set to true, the method for checking permissions will be registered on the gate.
     * Set this to false if you want to implement custom logic for checking permissions.
     */

    'register_permission_check_method' => true,

    /*
     * When set to true, the required permission names are added to exception messages.
     * This could be considered an information leak in some contexts, so the default
     * is false here for optimum safety.
     */

    'display_permission_in_exception' => false,

    /*
     * When set to true, the required role names are added to exception messages.
     * This could be considered an information leak in some contexts, so the default
     * is false here for optimum safety.
     */

    'display_role_in_exception' => false,

    /*
     * By default wildcard permission lookups are disabled.
     */

    'enable_wildcard_permission' => false,

    /*
     * The class to use for interpreting wildcard permissions.
     * If you need to modify delimiters, etc., you can create your own
     * class and pass it here.
     */
    // 'wildcard_permission' => Spatie\Permission\WildcardPermission::class,

    /*
     * Multi-tenancy: this project does NOT use teams, so this stays false.
     */
    'teams' => false,

    /*
     * The class to use to resolve the permissions team id.
     */
    'team_resolver' => Spatie\Permission\DefaultTeamResolver::class,

    /*
     * When set to true, the required permission names and team id are added to exception messages.
     */
    'use_passport_client_credentials' => false,

    /*
     * Passport client credentials grant feature.
     */

    'display_permission_in_exception_team_id' => false,

    'cache' => [

        /*
         * When checking for a permission against a model by passing a Guard instance to the
         * check, this key determines what attribute on the Guard instance is used to call
         * the cache store you wish to use for permission and role caching. You may also
         * set this value to "default" to use the application's default cache store.
         */

        'store' => 'default',

        /*
         * Cache key, and optionally cache store, used to store all the cached permission data.
         */

        'key' => 'spatie.permission.cache',

    ],

];
