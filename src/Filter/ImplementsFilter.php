<?php
namespace Scancore\Filter;

class ImplementsFilter implements IFilter
{
    private array $interfaces;

    /**
     * @param array $interfaces Список имён интерфейсов (полностью квалифицированных)
     */
    public function __construct(array $interfaces)
    {
        $this->interfaces = $interfaces;
    }

    public function accept(string $relativePath, string $fullPath, array $context = []): bool
    {
        // Требует наличия карты классов в контексте
        if (!isset($context['classMap']) || !is_array($context['classMap'])) {
            return false;
        }

        $classMap = $context['classMap'];

        // Инвертируем карту, чтобы получить файл -> классы, определённые в нём
        $fileToClasses = [];
        foreach ($classMap as $class => $file) {
            $fileToClasses[$file][] = $class;
        }

        if (!isset($fileToClasses[$relativePath])) {
            return false; // в файле нет определений классов
        }

        $classesInFile = $fileToClasses[$relativePath];

        // Для каждого класса в файле проверяем, реализует ли он хотя бы один из указанных интерфейсов
        // Это потребовало бы полного отражения, но для простоты используем поиск строки "implements"
        // Более точный способ — анализировать код, но для производительности ограничимся строковым поиском.
        $content = file_get_contents($fullPath);
        if ($content === false) {
            return false;
        }

        foreach ($this->interfaces as $interface) {
            // Ищем "implements ... $interface" или "implements ... $interface,"
            // или "implements ... $interface {"
            $shortName = $this->getShortName($interface);
            if (preg_match('/implements\s+([^{]+)/', $content, $matches)) {
                $implementList = $matches[1];
                if (strpos($implementList, $shortName) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getShortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }
}