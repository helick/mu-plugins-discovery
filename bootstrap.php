<?php

// Require Composer autoloader if installed on it's own
if (file_exists($composer = __DIR__ . '/vendor/autoload.php')) {
    require_once $composer;
}

// Register command
\Helick\MUPluginsDiscovery\Command::register();
