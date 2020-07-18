<?php

//
// Copyright (C) 2020-2020 Next Generation CMS (http://ngcms.ru/)
// Name: statistics.rpc.php
// Description: RPC library for STATISTICS module
// Author: NGCMS Development Team
//

// Protect against hack attempts
if (!defined('NGCMS')) {
    die('HAL');
}

// Calculate cache size
function getCacheSize($params) {

    // Check for permissions
    if (!checkPermission(array('plugin' => '#admin', 'item' => 'cache'), null, 'modify')) {
        ngSYSLOG(array('plugin' => '#admin', 'item' => 'cache'), array('action' => 'getCacheSize'), null, array(0, 'SECURITY.PERM'));

        return array('status' => 0, 'errorCode' => 2, 'errorText' => 'Access denied (perm)');
    }

    // Check for security token
    if ((!isset($params['token'])) || ($params['token'] != genUToken('admin.statistics'))) {
        ngSYSLOG(array('plugin' => '#admin', 'item' => 'rewrite'), array('action' => 'modify'), null, array(0, 'SECURITY.TOKEN'));

        return array('status' => 0, 'errorCode' => 2, 'errorText' => 'Access denied (token)');
    }

    $dir = root.'cache/';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);

    $stat = [
        'numFiles' => 0,
        'numDir' => 0,
        'size' => 0,
        'error' => '',
    ];
    try {
        foreach ($files as $fname => $fileinfo) {
            // Skip .htaccess
            if ($fileinfo->getFilename() == '.htaccess') {
                continue;
            }

            if ($fileinfo->isDir()) {
                $stat['numDir']++;
            } else {
                $stat['numFiles']++;
                $stat['size'] += filesize($fname);
            }
        }
    } catch (UnexpectedValueException $e) {
        $stat['error'] = $e->getMessage(); //'Error reading data from cache directory';
        return ['status' => 0, 'errorCode' => 1, 'errorText' => $stat['error']];
    }

    return ['status' => 1, 'errorCode' => 0, 'errorText' => 'Done', 'numFiles' => $stat['numFiles'], 'numDir' => $stat['numDir'], 'size' => Formatsize($stat['size'])];
}

// Clean file cache
function cleanCache($params) {

    // Check for permissions
    if (!checkPermission(array('plugin' => '#admin', 'item' => 'cache'), null, 'modify')) {
        ngSYSLOG(array('plugin' => '#admin', 'item' => 'cache'), array('action' => 'getCacheSize'), null, array(0, 'SECURITY.PERM'));

        return array('status' => 0, 'errorCode' => 2, 'errorText' => 'Access denied (perm)');
    }

    // Check for security token
    if ((!isset($params['token'])) || ($params['token'] != genUToken('admin.statistics'))) {
        ngSYSLOG(array('plugin' => '#admin', 'item' => 'rewrite'), array('action' => 'modify'), null, array(0, 'SECURITY.TOKEN'));

        return array('status' => 0, 'errorCode' => 2, 'errorText' => 'Access denied (token)');
    }

    $dir = root.'cache/';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);

    try {
        foreach ($files as $fname => $fileinfo) {
            // Skip .htaccess
            if ($fileinfo->getFilename() == '.htaccess') {
                continue;
            }

            if ($fileinfo->isDir()) {
                rmdir($fname);
            } else {
                unlink($fname);
            }
        }
    } catch (UnexpectedValueException $e) {
        $stat['error'] = $e->getMessage(); //'Error reading data from cache directory';
        return ['status' => 0, 'errorCode' => 1, 'errorText' => $stat['error']];
    }

    return ['status' => 1, 'errorCode' => 0, 'errorText' => 'Done'];
}


if (function_exists('rpcRegisterAdminFunction')) {
    rpcRegisterAdminFunction('admin.statistics.getCacheSize', 'getCacheSize');
    rpcRegisterAdminFunction('admin.statistics.cleanCache', 'cleanCache');
}