<?php
/**
 * PHP EPub Meta - App public entrypoint
 *
 * @author mikespub
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use SebLucas\EPubMeta\App\Handler;

// modify this to point to your book directory
$bookdir = '/home/andi/Dropbox/ebooks/';
$bookdir = dirname(__DIR__) . '/test/data/';

$handler = new Handler($bookdir);
try {
    $handler->handle();
} catch (Throwable $e) {
    error_log($e);
    echo $e->getMessage();
}
return;
