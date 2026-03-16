<?php
namespace Scancore\Console;

/**
 * Парсер аргументов командной строки.
 * Поддерживает позиционные аргументы и опции вида --name=value.
 * Повторяющиеся опции собираются в массив.
 */
class Input
{
    private array $arguments = [];
    private array $options = [];

    /**
     * @param array $argv Аргументы командной строки (без имени скрипта).
     */
    public function __construct(array $argv)
    {
        foreach ($argv as $arg) {
            if (strpos($arg, '--') === 0) {
                $parts = explode('=', substr($arg, 2), 2);
                $name = $parts[0];
                $value = $parts[1] ?? null;

                if ($value === null) {
                    continue;
                }

                if (isset($this->options[$name])) {
                    if (!is_array($this->options[$name])) {
                        $this->options[$name] = [$this->options[$name]];
                    }
                    $this->options[$name][] = $value;
                } else {
                    $this->options[$name] = $value;
                }
            } else {
                $this->arguments[] = $arg;
            }
        }
    }

    /**
     * Возвращает позиционные аргументы.
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Возвращает значение опции (всегда массив, даже если один элемент).
     */
    public function getOption(string $name): array
    {
        $value = $this->options[$name] ?? [];
        return is_array($value) ? $value : [$value];
    }

    /**
     * Проверяет, присутствует ли опция.
     */
    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }
}