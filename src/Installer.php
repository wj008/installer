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
        'sdopx-plugin' => true,
        'beacon-widget' => true,
        'beacon-app' => true,
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
            if (in_array($name, $processedPackages)) {
                continue;
            }
            $processedPackages[] = $name;
            $packagePath = $this->composer->getInstallationManager()->getInstallPath($package);
            $packagePath = $packagePath . DIRECTORY_SEPARATOR . '.install';
            if (is_dir($packagePath)) {
                $directory = new \RecursiveDirectoryIterator($packagePath, \RecursiveDirectoryIterator::SKIP_DOTS);
                foreach ($directory as $dir) {
                    if ($dir->isDir()) {
                        $sourcePath = $dir->getPathname();
                        $targetPath = getcwd() . DIRECTORY_SEPARATOR . $dir->getBasename();
                        $changed = $this->copy($sourcePath, $targetPath);
                        if ($changed) {
                            $this->io->write('- Installing <info>' . $name . '</info>');
                        }
                    }
                }
            }
            $this->removeDir($packagePath);
        }
    }

    private function removeDir($path)
    {
        if (is_dir($path)) {
            $p = scandir($path);
            if (count($p) > 2) {
                foreach ($p as $val) {
                    if ($val != "." && $val != "..") {
                        if (is_dir($path . DIRECTORY_SEPARATOR . $val)) {
                            $this->removeDir($path . DIRECTORY_SEPARATOR . $val);
                        } else {
                            unlink($path . DIRECTORY_SEPARATOR . $val);
                        }
                    }
                }
            }
        }
        rmdir($path);

    }

    private function copy(string $sourcePath, string $targetPath)
    {
        $changed = false;
        $directory = new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $fileInfo) {
            $target = $targetPath . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($fileInfo->isDir()) {
                if (!is_dir($target)) {
                    $this->io->write('- Add Dir <info>' . $target . '</info>');
                    mkdir($target);
                }
            } elseif (!file_exists($target)) {
                $this->io->write('- Add File <info>' . $target . '</info>');
                $this->copyFile($fileInfo->getPathname(), $target);
                $changed = true;
            }
        }
        return $changed;
    }

    public function copyFile(string $source, string $target)
    {
        if (file_exists($target)) {
            return;
        }
        copy($source, $target);
        @chmod($target, fileperms($target) | (fileperms($source) & 0111));
    }

}