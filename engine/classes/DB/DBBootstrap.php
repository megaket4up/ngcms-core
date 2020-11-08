<?php

namespace NG\DB;

use NG\Core\Container;

class DBBootstrap
{
    public static function boot(Container $container)
    {
        global $mysql, $config;

        $container->set('db', new PDODriver([
            'host' => $config['dbhost'],
            'user' => $config['dbuser'],
            'pass' => $config['dbpasswd'],
            'db' => $config['dbname'],
            'charset' => 'utf8'
        ]));

        $container->set('config', $config);
        $container->set('legacyDB', new NGLegacyDB(false));
        $container->getLegacyDB()->connect('', '', '');
        $mysql = $container->getLegacyDB();
    
        // Sync PHP <=> MySQL timezones
        $mysql->query('SET @@session.time_zone = "'.date('P').'"');
    }
}