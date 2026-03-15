<?php
namespace Scancore\Console;

use Scancore\IgnoreInitializer;

class InitCommand implements ICommand
{
    public function execute(array $args): void
    {
        $force = in_array('--force', $args) || in_array('-f', $args);
        $root = getcwd();

        $initializer = new IgnoreInitializer();
        $created = $initializer->ensure($root, $force, false);

        if ($created) {
            echo ".scancoreignore успешно создан" . ($force ? " (перезаписан)" : "") . ".\n";
        } else {
            echo ".scancoreignore уже существует. Используйте --force для перезаписи.\n";
        }
    }
}