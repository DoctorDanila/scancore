<?php
namespace Scancore\Filter;

class ClassFilter implements IFilter
{
    private array $classNames;

    /**
     * @param array $classNames Список имён классов (полностью квалифицированных)
     */
    public function __construct(array $classNames)
    {
        $this->classNames = $classNames;
    }

    public function accept(string $relativePath, string $fullPath, array $context = []): bool
    {
        // Проверяем содержимое файла на наличие имени класса
        $content = file_get_contents($fullPath);
        if ($content === false) {
            return false;
        }

        foreach ($this->classNames as $className) {
            // Экранируем обратные слеши для regex
            $pattern = '/' . preg_quote($className, '/') . '/';
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }
}