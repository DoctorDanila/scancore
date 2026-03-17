<?php
namespace Scancore\Filter;

class FilterManager
{
    /** @var IFilter[] */
    private array $filters = [];

    public function addFilter(IFilter $filter): void
    {
        $this->filters[] = $filter;
    }

    /**
     * Применяет все добавленные фильтры к списку путей.
     * Возвращает отфильтрованный массив путей, которые прошли все фильтры.
     *
     * @param array $paths
     * @param string $root
     * @param array $context
     * @return array
     */
    public function apply(array $paths, string $root, array $context = []): array
    {
        if (empty($this->filters)) {
            return $paths;
        }

        $result = [];
        foreach ($paths as $relativePath) {
            $fullPath = $root . '/' . $relativePath;
            if (!is_file($fullPath)) {
                continue; // пропускаем директории
            }

            $accept = true;
            foreach ($this->filters as $filter) {
                if (!$filter->accept($relativePath, $fullPath, $context)) {
                    $accept = false;
                    break;
                }
            }

            if ($accept) {
                $result[] = $relativePath;
            }
        }

        return $result;
    }

    /**
     * Фильтрует массив зависимостей (dependents), оставляя только те файлы,
     * которые прошли фильтрацию.
     *
     * @param array $dependents
     * @param array $allowedFiles
     * @return array
     */
    public function filterDependencies(array $dependents, array $allowedFiles): array
    {
        $filtered = [];
        $allowedSet = array_flip($allowedFiles);

        foreach ($dependents as $file => $depList) {
            if (!isset($allowedSet[$file])) {
                continue;
            }

            $filteredList = array_filter($depList, function($dep) use ($allowedSet) {
                return isset($allowedSet[$dep['file']]);
            });

            if (!empty($filteredList)) {
                $filtered[$file] = array_values($filteredList);
            }
        }

        return $filtered;
    }
}