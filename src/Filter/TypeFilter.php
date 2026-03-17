<?php
namespace Scancore\Filter;

class TypeFilter implements IFilter
{
    private array $typeNames;

    /**
     * @param array $typeNames Список подстрок для поиска в имени файла (например, "Controller", "Model")
     */
    public function __construct(array $typeNames)
    {
        $this->typeNames = $typeNames;
    }

    public function accept(string $relativePath, string $fullPath, array $context = []): bool
    {
        foreach ($this->typeNames as $type) {
            if (stripos($relativePath, $type) !== false) {
                return true;
            }
        }
        return false;
    }
}