<?php
namespace Scancore\Filter;

class ExtendsFilter implements IFilter
{
    private array $parents;

    public function __construct(array $parents)
    {
        $this->parents = $parents;
    }

    public function accept(string $relativePath, string $fullPath, array $context = []): bool
    {
        $content = file_get_contents($fullPath);
        if ($content === false) {
            return false;
        }

        foreach ($this->parents as $parent) {
            $shortName = $this->getShortName($parent);
            if (preg_match('/extends\s+([^{]+)/', $content, $matches)) {
                $extendsList = $matches[1];
                if (strpos($extendsList, $shortName) !== false) {
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