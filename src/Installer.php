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
    private Composer $composer;

    /** @var IOInterface */
    private IOInterface $io;

    private array $supportedTypes = [
        'sdopx-plugin' => ['sdopx/plugin'],
        'beacon-widget' => ['beacon/widget'],
        'app' => ['app'],
    ];

    /**
     * @return array[]
     */
    public static function getSubscribedEvents(): array
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

    public function install(): void
    {
        $extra = $this->composer->getPackage()->getExtra();
        $disable = $extra['installer-disable'] ?? false;
        if ($disable) {
            $this->io->write('<info>Beacon Installer was disabled</info>');
            return;
        }

        foreach ($this->projectTypes as $projectType => $paths) {
            if ($this->hasPath($paths)) {
                $this->installProjectType($projectType);
            }
        }
    }

    private function hasPath(array $paths): bool
    {
        foreach ($paths as $path) {
            $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            if (!file_exists(getcwd() . DIRECTORY_SEPARATOR . $path)) {
                return false;
            }
        }
        return true;
    }

    private function installProjectType(string $projectType): void
    {
        $processedPackages = [];
        $packages = $this->composer->getRepositoryManager()->getLocalRepository()->getPackages();
        foreach ($packages as $package) {
            $name = $package->getName();
            if (in_array($name, $processedPackages)) {
                continue;
            }
            $processedPackages[] = $name;
            $packagePath = $this->composer->getInstallationManager()->getInstallPath($package);
            $sourcePath = $packagePath . DIRECTORY_SEPARATOR . '.install' . DIRECTORY_SEPARATOR . $projectType;
            if (file_exists($sourcePath)) {
                $changed = $this->copy($sourcePath, (string)getcwd());
                if ($changed) {
                    $this->io->write('- Configured <info>' . $name . '</info>');
                }
            }
        }
    }

    private function copy(string $sourcePath, string $targetPath): bool
    {
        $changed = false;
        /** @var \RecursiveDirectoryIterator $iterator */
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        /** @var \SplFileInfo $fileInfo */
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

    public function copyFile(string $source, string $target): void
    {
        if (file_exists($target)) {
            return;
        }
        copy($source, $target);
        @chmod($target, fileperms($target) | (fileperms($source) & 0111));
    }


}