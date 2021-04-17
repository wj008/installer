<?php
declare(strict_types=1);

namespace beacon\install;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;


final class Installer implements PluginInterface, EventSubscriberInterface
{

    /** @var Composer */
    private $composer;

    /** @var IOInterface */
    private $io;

    private $projectTypes = [
        'sdopx-plugin' => ['sdopx/plugin'],
        'beacon-widget' => ['beacon/widget', 'www', 'app/tool/widget'],
        'beacon-app' => ['app', 'www'],
    ];

    /**
     * @return array[]
     */
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => ['install', 1],
            ScriptEvents::POST_UPDATE_CMD => ['install', 1],
        ];
    }

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {

    }

    public function uninstall(Composer $composer, IOInterface $io)
    {

    }

    public function install()
    {
        $extra = $this->composer->getPackage()->getExtra();
        $disable = isset($extra['installer-disable']) ? $extra['installer-disable'] : false;
        if ($disable) {
            $this->io->write('<info>Beacon Installer was disabled</info>');
            return;
        }
        $processedPackages = [];
        $packages = $this->composer->getRepositoryManager()->getLocalRepository()->getPackages();
        foreach ($packages as $package) {
            $name = $package->getName();
            $type = $package->getType();
            if (!isset($this->projectTypes[$type])) {
                continue;
            }
            $paths = $this->projectTypes[$type];
            if (in_array($name, $processedPackages)) {
                continue;
            }
            $processedPackages[] = $name;
            $packagePath = $this->composer->getInstallationManager()->getInstallPath($package);
            foreach ($paths as $path) {
                $sourcePath = $packagePath . DIRECTORY_SEPARATOR . $path;
                $targetPath = getcwd() . DIRECTORY_SEPARATOR . $path;
                if (file_exists($sourcePath)) {
                    $changed = $this->copy($sourcePath, $targetPath);
                    if ($changed) {
                        $this->io->write('- Installing <info>' . $name . '</info>');
                    }

                }
            }
            $this->delDir($packagePath);
        }
    }

    private function delDir($path)
    {
        if (is_dir($path)) {
            $p = scandir($path);
            if (count($p) > 2) {
                foreach ($p as $val) {
                    if ($val != "." && $val != "..") {
                        if (is_dir($path . DIRECTORY_SEPARATOR . $val)) {
                            $this->delDir($path . DIRECTORY_SEPARATOR . $val);
                        } else {
                            unlink($path . DIRECTORY_SEPARATOR . $val);
                        }
                    }
                }
            }
        }
        return rmdir($path);

    }

    private function copy(string $sourcePath, string $targetPath)
    {
        $changed = false;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $fileInfo) {
            $target = $targetPath . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($fileInfo->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target);
                }
            } elseif (!file_exists($target)) {
                $this->copyFile($fileInfo->getPathname(), $target);
                $changed = true;
            }
        }
        return $changed;
    }

    public function copyFile(string $source, string $target)
    {
        $this->io->write('- Add File <info>' . $target . '</info>');
        if (file_exists($target)) {
            return;
        }
        copy($source, $target);
        @chmod($target, fileperms($target) | (fileperms($source) & 0111));
    }

}