<?php
namespace Scancore\Console;

use Scancore\IgnoreInitializer;
use Scancore\IgnoreOptionHandler;

class InitCommand implements ICommand
{
    public function execute(Input $input): void
    {
        $args = $input->getArguments();
        $force = in_array('--force', $args) || in_array('-f', $args); // опции пока не парсятся через Input, но для обратной совместимости оставляем
        $root = getcwd();

        // Для init команды временные игноры не применяются (см. .env по умолчанию)
        $ignoreHandler = new IgnoreOptionHandler($input, 'init', $root);
        $ignoreHandler->getPatterns(); // вызываем для возможного предупреждения

        $initializer = new IgnoreInitializer();
        $created = $initializer->ensure($root, $force, false);

        if ($created) {
            echo ".scancoreignore успешно создан" . ($force ? " (перезаписан)" : "") . ".\n";
        } else {
            echo ".scancoreignore уже существует. Используйте --force для перезаписи.\n";
        }
    }
}