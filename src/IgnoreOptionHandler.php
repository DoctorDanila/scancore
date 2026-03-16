<?php
namespace Scancore;

use Scancore\Console\Input;

class IgnoreOptionHandler
{
    private Input $input;
    private string $commandName;
    private string $rootDir;

    public function __construct(Input $input, string $commandName, string $rootDir)
    {
        $this->input = $input;
        $this->commandName = $commandName;
        $this->rootDir = rtrim($rootDir, '/') . '/';
    }

    /**
     * Возвращает массив паттернов для временного игнорирования,
     * учитывая настройки из .env.
     *
     * @return array
     */
    public function getPatterns(): array
    {
        $env = EnvLoader::load($this->rootDir);
        $ignorePatterns = $this->input->getOption('ignore');

        if (empty($ignorePatterns)) {
            return [];
        }

        // Если .env отсутствует - предупреждаем и игнорируем паттерны
        if (empty($env)) {
            fwrite(STDERR, "Warning: .env file not found, --ignore options will be ignored.\n");
            return [];
        }

        // Список команд-исключений из .env (по умолчанию пустой)
        $exceptCommands = array_map('trim', explode(',', $env['IGNORE_EXCEPT_COMMANDS'] ?? ''));

        if (in_array($this->commandName, $exceptCommands, true)) {
            fwrite(STDERR, "Warning: --ignore options are not applied to the '{$this->commandName}' command (see .env).\n");
            return [];
        }

        return $ignorePatterns;
    }
}