<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Module Discovery
    |--------------------------------------------------------------------------
    |
    | When "auto_discover" is true, every directory inside "path" is treated as
    | a module. Use "enabled" as an allow-list or "disabled" to exclude
    | specific modules. An empty "enabled" list means all discovered modules
    | are enabled.
    |
    */

    'path' => app_path('Modules'),

    'auto_discover' => true,

    'enabled' => [],

    'disabled' => [],

];
