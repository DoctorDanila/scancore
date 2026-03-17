<?php
namespace Scancore\Filter;

class PatternFilter implements IFilter
{
    private array $patterns;

    /**
     * @param array $patterns Список регулярных выражений
     */
    public function __construct(array $patterns)
    {
        $this->patterns = $patterns;
    }

    public function accept(string $relativePath, string $fullPath, array $context = []): bool
    {
        $content = file_get_contents($fullPath);
        if ($content === false) {
            return false;
        }

        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }
}