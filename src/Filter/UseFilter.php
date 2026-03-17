<?php
namespace Scancore\Filter;

class UseFilter implements IFilter
{
    private array $usedItems; // имена классов/трейтов

    public function __construct(array $usedItems)
    {
        $this->usedItems = $usedItems;
    }

    public function accept(string $relativePath, string $fullPath, array $context = []): bool
    {
        $content = file_get_contents($fullPath);
        if ($content === false) {
            return false;
        }

        // Ищем строки use (не включая группы use с запятыми для простоты)
        foreach ($this->usedItems as $item) {
            $shortName = $this->getShortName($item);
            // Ищем "use ... $shortName;" или "use ... $shortName as ...;"
            if (preg_match('/use\s+([^;]+?)(?:\s+as\s+[^;]+)?;/', $content, $matches)) {
                $useClause = $matches[1];
                if (strpos($useClause, $shortName) !== false || strpos($useClause, $item) !== false) {
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