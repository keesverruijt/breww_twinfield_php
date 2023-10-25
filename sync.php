#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Monolog\Level;
use Monolog\Logger;

$getOpt = new \GetOpt\GetOpt([

    \GetOpt\Option::create('d', 'debug', \GetOpt\GetOpt::NO_ARGUMENT)
        ->setDescription('Enable debug logging'),

    \GetOpt\Option::create('b', 'browse', \GetOpt\GetOpt::REQUIRED_ARGUMENT)
        ->setDescription('Browse data'),

    \GetOpt\Option::create('c', 'customers', \GetOpt\GetOpt::NO_ARGUMENT)
        ->setDescription('Show list of customers'),

    \GetOpt\Option::create('o', 'offices', \GetOpt\GetOpt::NO_ARGUMENT)
        ->setDescription('Show list of offices'),

    \GetOpt\Option::create('v', 'vat', \GetOpt\GetOpt::NO_ARGUMENT)
        ->setDescription('Show list of vat codes'),
]);
try {
    try {
        $getOpt->process();
    } catch (\GetOpt\Missing $exception) {
        // catch missing exceptions if help is requested
        if (!$getOpt->getOption('help')) {
            throw $exception;
        }
    }
} catch (\GetOpt\ArgumentException $exception) {
    file_put_contents('php://stderr', $exception->getMessage() . PHP_EOL);
    echo PHP_EOL . $getOpt->getHelpText();
    exit(1);
}

$level = Level::Info;
$last_argc = 0;
if ($getOpt->getOption('debug')) {
  $level = Level::Debug;
}

$log = new \Monolog\Logger('sync');
$handler = new \Monolog\Handler\StreamHandler('php://stderr', $level);
$handler->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true, true));
$log->pushHandler($handler);

include 'sync.inc';

?>
