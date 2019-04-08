<?php

return [
    /* You can set user parameters as you wish.
     *
     */
    'user' => [
        'model' => [
            'web' => [
                'model' => \App\Models\User::class
            ],
            'api' => [
                'model' => \App\Models\User::class
            ],//models are differentiated by guard
        ],
        'fields' => [
            'id' => [
                'field_name' => 'id',
                'order' => 0
            ],
            'state' => [
                /*
                 * You can set the name of the state field
                 */
                'field_name' => 'state',
                /*
                 * You can set enabled/disabled values for the user and the default value in case of not both matching.
                 */
                'values' => [
                    'disabled' => [0],
                    'enabled' => [1],
                    'default' => false //if any of the above values is catched this will be the result of isEnabled function
                ],
                'order' => 4
            ],
            'name' => [
                'field_name' => 'name',
                'order' => 1
            ],
            'surname' => [
                'field_name' => 'surname',
                'order' => 2
            ],
            'username' => [
                'field_name' => 'email',
                'order' => 3,
                'rules' => 'email'
            ],
            'password' => [
                'field_name' => 'password',
                'order' => 5
            ]
        ],
    ],

    'container' => [
        'request' => [
            // This is the key that is used when requests are fetched and parsed,
            // for instance $request->input('container_id')
            'key' => 'container_id',
        ]
    ],


    'labels' => [
        'container' => [
            'name' => [
                'plural' => [
                    'it' => 'Siti',
                    'en' => 'Containers'
                ],
                'singular' => [
                    'it' => 'Sito',
                    'en' => 'Container'
                ]
            ]
        ],
        'users' => [
            'username' => [
                'it' => 'Email',
                'en' => 'Email'
            ]
        ]
    ],


    'models' => [

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your permissions. Of course, it
         * is often just the "Permission" model but you may use whatever you like.
         *
         * The model you want to use as a Permission model needs to implement the
         * `Idsign\Permission\Contracts\Permission` contract.
         */

        'permission' => Idsign\Permission\Models\Permission::class,

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your roles. Of course, it
         * is often just the "Role" model but you may use whatever you like.
         *
         * The model you want to use as a Role model needs to implement the
         * `Idsign\Permission\Contracts\Role` contract.
         */

        'role' => Idsign\Permission\Models\Role::class,

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your roles. Of course, it
         * is often just the "Section" model but you may use whatever you like.
         *
         * The model you want to use as a Role model needs to implement the
         * `Idsign\Permission\Contracts\Section` contract.
         */

        'section' => Idsign\Permission\Models\Section::class,

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your roles. Of course, it
         * is often just the "Container" model but you may use whatever you like.
         *
         * The model you want to use as a Role model needs to implement the
         * `Idsign\Permission\Contracts\Container` contract.
         */

        'container' => Idsign\Permission\Models\Container::class,

    ],

    'table_names' => [

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your roles. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */

        'roles' => 'roles',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your permissions. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */

        'permissions' => 'permissions',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your sections. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */

        'sections' => 'sections',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your models permissions. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'model_has_permissions' => 'model_has_permissions',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your models roles. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'model_has_roles' => 'model_has_roles',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your roles permissions. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'role_has_permissions' => 'role_has_permissions',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your roles permissions. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'containers' => 'containers',

        /*
         * When sections' tree is generated it is done by a container key.
         * This is the pivot table. We have chosen a basic default value but
         * you may easily change it to any table you like.
         */

        'container_section' => 'container_section',

        /*
         * When sections' tree is generated it is done by a container key.
         * This is the pivot table. We have chosen a basic default value but
         * you may easily change it to any table you like.
         */

        'container_role' => 'container_role',
    ],

    /*
     * These are parameter used by CrudMiddleware where as key we have the final part of the crud route name (for example a route named 'page.store'
     * where page could be the controller and store is the index in the array, and as value we have the permission name. You can call the middleware
     * for example by $this->middleware('crud:blog') where blog is the section. By default other not crud routes are allowed, if you want to not allow
     * other routes use not_nullable option, such as $this->middleware('crud:blog,not_nullable')
     * */
    'crud' => [
        'index' => 'read',
        'create' => 'create',
        'destroy' => 'destroy',
        'edit' => 'update',
        'store' => 'create',
        'show' => 'read',
        'update' => 'update',
        'update_destroy' => [
            'destroy',
            'update'
        ]
    ],

    'roles' => [
        'superuser' => 'superuser',
        'admin' => 'admin',
        'operator' => 'operator'
    ],

    /*
     * By default all permissions will be cached for 24 hours unless a permission or
     * role is updated. Then the cache will be flushed immediately.
     */

    'cache_expiration_time' => 60 * 24,
];
