<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
        'permisos' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'pruebas_backend_providencia'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
        
          'providencia_renueva_bigbag' => [
            'driver' => 'mysql',
            'host' => env('RENUEVA_BIGBAG_DB_HOST', 'localhost'),
            'port' => env('RENUEVA_BIGBAG_DB_PORT', '3306'),
            'database' => env('RENUEVA_BIGBAG_DB_DATABASE', 'providencia_renueva_bigbag'),
            'username' => env('RENUEVA_BIGBAG_DB_USERNAME', 'providencia_renueva_bigbag'),
            'password' => env('RENUEVA_BIGBAG_DB_PASSWORD', 'Pr0v1d3nc14_r3nu3v4'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
             'colaboradores' => [
            'driver' => 'mysql',
            'host' => env('COLABORADORES_DB_HOST', 'localhost'),
            'port' => env('COLABORADORES_DB_PORT', '3306'),
            'database' => env('COLABORADORES_DB_DATABASE', 'providencia_solicitud_de_permisos'),
            'username' => env('COLABORADORES_DB_USERNAME', 'providencia_solicitud_de_permisos_laborales'),
            'password' => env('COLABORADORES_DB_PASSWORD', 'Pr0v1d3nc14$#2025*'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
        
        'terminacion_empaque' => [
            'driver' => 'mysql',
            'host' => env('TERMINACION_EMPAQUE_DB_HOST', 'localhost'),
            'port' => env('TERMINACION_EMPAQUE_DB_PORT', '3306'),
            'database' => env('TERMINACION_EMPAQUE_DB_DATABASE', 'providencia_terminacion_empaque'),
            'username' => env('TERMINACION_EMPAQUE_DB_USERNAME', 'providencia_terminacion_empaque'),
            'password' => env('TERMINACION_EMPAQUE_DB_PASSWORD', 'Pr0v1d3nc14$#2025*'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
        
        'conteo_inventario' => [
            'driver' => 'mysql',
            'host' => env('CONTEO_INVENTARIO_DB_HOST', 'localhost'),
            'port' => env('CONTEO_INVENTARIO_DB_PORT', '3306'),
            'database' => env('CONTEO_INVENTARIO_DB_DATABASE', 'providencia_conteo_inventario'),
            'username' => env('CONTEO_INVENTARIO_DB_USERNAME', 'providencia_conteo_inventario'),
            'password' => env('CONTEO_INVENTARIO_DB_PASSWORD', 'Pr0v1d3nc14$#2025*'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_SAINT_URL'),
            'host' => env('DB_SAINT_HOST', 'saintdb.c0vtmcyw0ccb.us-east-1.rds.amazonaws.com'),
            'port' => env('DB_SAINT_PORT', '5432'),
            'database' => env('DB_SAINT_DATABASE', 'saint'),
            'username' => env('DB_SAINT_USERNAME', 'cfipadmin'),
            'password' => env('DB_SAINT_PASSWORD', 'cF1p_2022#'),
            'charset' => env('DB_SAINT_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'siesa' => [
            'driver' => 'sqlsrv',
            'host' => env('DB_SIESA_HOST', 'siesa-m3-sqlsw-db07.cihpfbkcx35e.us-east-1.rds.amazonaws.com'),
            'port' => env('DB_SIESA_PORT', '1433'),
            'database' => env('DB_SIESA_DATABASE', 'UnoEE_CFIProvi_Real'),
            'username' => env('DB_SIESA_USERNAME', 'cfiprovidencia'),
            'password' => env('DB_SIESA_PASSWORD', 'Cfiprovidencia$12$%'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

         'solicitud_de_permisos_local' => [
        'driver' => env('PRUEBA_CONNECTION', 'mysql'),
        'host' => env('PRUEBA_DB_HOST', '127.0.0.1'),
        'port' => env('PRUEBA_DB_PORT', '3306'),
        'database' => env('PRUEBA_DB_DATABASE', 'forge'),
        'username' => env('PRUEBA_DB_USERNAME', 'forge'),
        'password' => env('PRUEBA_DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
