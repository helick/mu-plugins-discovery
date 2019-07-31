<?php

namespace Helick\MUPluginsDiscovery;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * The composer instance.
     *
     * @var Composer
     */
    private $composer;

    /**
     * The IO instance.
     *
     * @var IOInterface
     */
    private $io;

    /**
     * @inheritDoc
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io       = $io;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'post-autoload-dump' => ['discover'],
        ];
    }

    /**
     * Rebuild the cached must-use plugins manifest.
     *
     * @return void
     */
    public function discover(): void
    {
        $finder = new Finder();
        $finder->in(getcwd() . '/web/content/mu-plugins')
               ->files()->name('*.php')
               ->sortByName();

        $pluginsFinder   = (clone $finder)->depth(1);
        $muPluginsFinder = (clone $finder)->depth(0);

        $plugins   = $this->resolvePlugins($pluginsFinder);
        $muPlugins = $this->resolvePlugins($muPluginsFinder);

        foreach (array_column($plugins, 'Name') as $pluginName) {
            $this->io->write('Discovered plugin: ' . $pluginName, true, IOInterface::DEBUG);
        }

        $this->write(
            getcwd() . '/bootstrap/cache/mu-plugins.php',
            array_merge($plugins, $muPlugins)
        );

        $this->write(
            getcwd() . '/bootstrap/cache/plugins.php',
            array_keys($plugins)
        );

        $this->io->write('The must-use plugins manifest generated successfully.');
    }

    /**
     * Resolve plugins from a given finder.
     *
     * @param Finder $finder
     *
     * @return array
     */
    private function resolvePlugins(Finder $finder): array
    {
        $results = iterator_to_array($finder, false);

        $files = array_map(function (SplFileInfo $file) {
            return $file->getRelativePathname();
        }, $results);

        $data = array_map(function (SplFileInfo $file) {
            return $this->extractPluginData($this->extractDocBlock($file->getContents()));
        }, $results);

        $plugins = array_combine($files, $data);
        $plugins = array_filter($plugins, function (array $data) {
            return !empty($data['Name']);
        });

        return $plugins;
    }

    /**
     * Extract the doc block from a given source.
     *
     * @param string $source
     *
     * @return string
     */
    private function extractDocBlock(string $source): string
    {
        $comments = array_filter(token_get_all($source), function ($token) {
            return in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true);
        });

        $comment = array_shift($comments);

        return $comment[1];
    }

    /**
     * Extract the plugin data from a given source.
     *
     * @param string $source
     *
     * @return array
     */
    private function extractPluginData(string $source): array
    {
        $headers = [
            'Name'        => 'Plugin Name',
            'PluginURI'   => 'Plugin URI',
            'Version'     => 'Version',
            'Description' => 'Description',
            'Author'      => 'Author',
            'AuthorURI'   => 'Author URI',
            'TextDomain'  => 'Text Domain',
            'DomainPath'  => 'Domain Path',
            'Network'     => 'Network',
        ];

        $patterns = array_map(function (string $regex) {
            return '/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi';
        }, $headers);

        $matches = array_map(function (string $pattern) use ($source) {
            return preg_match($pattern, $source, $match) ? $match[1] : '';
        }, $patterns);

        $matches = array_map(function (string $match) {
            return trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $match));
        }, $matches);

        $data = array_combine(array_keys($headers), $matches);

        $data['Network']    = ('true' === strtolower($data['Network']));
        $data['Title']      = $data['Name'];
        $data['AuthorName'] = $data['Author'];

        return $data;
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
            $this->io->writeError("The {$directory} directory must be present and writable.");
        }

        file_put_contents(
            $path,
            '<?php return ' . var_export($manifest, true) . ';'
        );
    }
}
