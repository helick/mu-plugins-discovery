<?php

namespace Helick\MUPluginsDiscovery;

use WP_CLI;

final class Command
{
    /**
     * Register the command.
     *
     * @return void
     */
    public static function register(): void
    {
        WP_CLI::add_command('mu-plugins discover', static::class);
    }

    /**
     * Rebuild the cached must-use plugins manifest.
     *
     * @return void
     */
    public function __invoke(): void
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $discoveredPlugins = get_plugins('/../mu-plugins');
        $currentPlugins    = get_mu_plugins();

        foreach (array_column($discoveredPlugins, 'Name') as $pluginName) {
            WP_CLI::line("Discovered must-use plugin: <info>{$pluginName}</info>");
        }

        $this->write(
            getcwd() . '/bootstrap/cache/mu-plugins.php',
            $this->all($discoveredPlugins, $currentPlugins)
        );

        $this->write(
            getcwd() . '/bootstrap/cache/required-mu-plugins.php',
            $this->required($discoveredPlugins, $currentPlugins)
        );

        WP_CLI::success('The must-use plugins manifest generated successfully.');
    }

    /**
     * Get all must-use plugins.
     *
     * @param array $discoveredPlugins
     * @param array $currentPlugins
     *
     * @return array
     */
    private function all(array $discoveredPlugins, array $currentPlugins): array
    {
        // Mark discovered plugins
        $discoveredPlugins = array_map(function (array $plugin) {
            $plugin['Name'] .= ' *';

            return $plugin;
        }, $discoveredPlugins);

        $plugins = array_merge($discoveredPlugins, $currentPlugins);
        $plugins = array_unique($plugins, SORT_REGULAR);

        return $plugins;
    }

    /**
     * Get the required must-use plugins.
     *
     * @param array $discoveredPlugins
     * @param array $currentPlugins
     *
     * @return array
     */
    private function required(array $discoveredPlugins, array $currentPlugins): array
    {
        $plugins = array_diff_key($discoveredPlugins, $currentPlugins);
        $plugins = array_keys($plugins);

        return $plugins;
    }

    /**
     * Write the given manifest array to disk.
     *
     * @param string $path
     * @param array  $manifest
     *
     * @return void
     */
    private function write(string $path, array $manifest): void
    {
        if (!is_writable($directory = dirname($path))) {
            WP_CLI::error("The {$directory} directory must be present and writable.");
        }

        file_put_contents(
            $path,
            '<?php return ' . var_export($manifest, true) . ';'
        );
    }
}
