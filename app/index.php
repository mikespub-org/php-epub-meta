<?php

/**
 * PHP EPub Meta - App public entrypoint
 *
 * Use this as stand-alone web interface, or use the 6-step code in your own application
 *
 * @author mikespub
 */

if (!empty($_SERVER['SCRIPT_NAME']) && str_contains($_SERVER['SCRIPT_NAME'], '/vendor/')) {
    echo "Unable to run in /vendor/ directory";
    return;
}
require_once dirname(__DIR__) . '/vendor/autoload.php';

use SebLucas\EPubMeta\App\Handler as AppHandler;

// @todo modify this to point to your book directory
$bookdir = '/home/andi/Dropbox/ebooks/';
$bookdir = dirname(__DIR__) . '/test/data/';
//$bookdir = '/home/mikespub/seblucas-cops/tests/';

// allow ebooks in subfolders
$recursive = true;
// baseurl for assets etc. (relative to this entrypoint)
$baseurl = '..';
// rename file as new "$author-$title.epub" after update
$rename = false;
// template files directory
$templatedir = dirname(__DIR__) . '/templates/';
// cache directory for Google Books API calls (optional)
$cachedir = null;
$cachedir = dirname(__DIR__) . '/cache/';
// add parent link before home link
//$parent = ['title' => 'Parent', 'link' => '../../'];
$parent = [];

// 1. create config array with the options above
$config = [
    'bookdir' => $bookdir,
    'recursive' => $recursive,
    'baseurl' => $baseurl,
    'rename' => $rename,
    'templatedir' => $templatedir,
    'cachedir' => $cachedir,
    'parent' => $parent,
];
// 2. instantiate the app handler with the config array
$handler = new AppHandler($config);
try {
    // 3. get request params from PHP globals or elsewhere
    $params = $handler->getRequestFromGlobals();
    // 4. handle the request and get back the result
    $result = $handler->handle($params);
    if (!is_null($result)) {
        // 5.a. output the result
        echo $result;
    } else {
        // 5.b. some actions have no output, e.g. redirect after save
    }
} catch (Throwable $e) {
    // 6. catch any errors and handle them
    error_log($e);
    echo $e->getMessage();
}
return;
