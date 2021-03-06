<?php
/**
 * Class Twilight
 * @package philelson\Twilight
 * @author Phil Elson <phil@pegasus-commerce.com>
 */
require_once __DIR__.'/vendor/autoload.php';

use philelson\Twilight\Twilight;
use philelson\Twilight\Night;

$classes    = [new Twilight(), new Night()];
$executed   = false;

/** @var philelson\Twilight\AbstractWatcher $watcher */
foreach($classes as $watcher) {
    $name = strtolower($watcher->getThresholdName());

    if (false === in_array($name, $argv)) {
        continue;
    }

    $watcher->run();
    $executed = true;
}

if (false === $executed) {
    echo "Command does not match any watchers \n";
}
?>