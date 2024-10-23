<?php
/**
 * PHP EPub Meta - App public entrypoint
 *
 * @author mikespub
 */

if (!empty($_SERVER['SCRIPT_NAME']) && str_contains($_SERVER['SCRIPT_NAME'], '/vendor/')) {
    echo "Unable to run in /vendor/ directory";
    return;
}
require_once dirname(__DIR__) . '/vendor/autoload.php';

use SebLucas\EPubMeta\App\Handler;

// modify this to point to your book directory
$bookdir = '/home/andi/Dropbox/ebooks/';
$bookdir = dirname(__DIR__) . '/test/data/';

// baseurl for assets etc. (relative to this entrypoint)
$baseurl = '..';
// rename file as new "$author-$title.epub" after update
$rename = true;

$handler = new Handler($bookdir, $baseurl, $rename);
try {
    $result = $handler->handle();
    if (!is_null($result)) {
        echo $result;
    }
} catch (Throwable $e) {
    error_log($e);
    echo $e->getMessage();
}
return;
