<?php
namespace Scancore;

class IgnoreInitializer
{
    /**
     * Создаёт .scancoreignore, если он отсутствует, или перезаписывает при $force = true.
     *
     * @param string $rootDir Корневая директория проекта (с завершающим слешем или без).
     * @param bool $force Принудительная перезапись, если файл уже существует.
     * @param bool $quiet Не выводить сообщения в STDERR.
     * @return bool Было ли произведено создание/перезапись.
     */
    public function ensure(string $rootDir, bool $force = false, bool $quiet = false): bool
    {
        $rootDir = rtrim($rootDir, '/') . '/';
        $ignoreFile = $rootDir . '.scancoreignore';

        if (file_exists($ignoreFile) && !$force) {
            return false;
        }

        $gitignore = $rootDir . '.gitignore';
        if (file_exists($gitignore)) {
            $content = file_get_contents($gitignore);
            if ($content !== false) {
                file_put_contents($ignoreFile, $content);
                if (!$quiet) {
                    fwrite(STDERR, ".scancoreignore created from .gitignore\n");
                }
                return true;
            }
        }

        $default = $this->getDefaultContent();
        file_put_contents($ignoreFile, $default);
        if (!$quiet) {
            fwrite(STDERR, ".scancoreignore created with default rules\n");
        }
        return true;
    }

    private function getDefaultContent(): string
    {
        return <<<IGNORE
# Системные папки
.idea
.vscode
.DS_Store
.vagrant/

# Зависимости
/vendor
/node_modules

# Логи и временные файлы
*.log
/runtime

# Конфигурации окружения
.env
/phpunit.xml

# Git
.git/
.github/
IGNORE;
    }
}