<?php
namespace Scancore\Stats;

class Collector
{
    private string $root;
    private array $paths;

    public function __construct(string $root, array $paths)
    {
        $this->root = rtrim($root, '/') . '/';
        $this->paths = $paths;
    }

    public function collect(): array
    {
        $stats = [
            'total_files' => 0,
            'total_lines' => 0,
            'files_by_type' => [],
            'largest_files' => [],
            'activity_by_day' => [],
            'file_details' => [],
        ];

        $largest = [];

        foreach ($this->paths as $relativePath) {
            $fullPath = $this->root . $relativePath;
            if (!is_file($fullPath)) {
                continue;
            }

            $stats['total_files']++;
            $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) ?: 'unknown';
            $stats['files_by_type'][$ext] = ($stats['files_by_type'][$ext] ?? 0) + 1;

            $size = filesize($fullPath);
            $lines = $this->countLines($fullPath, $ext);
            $stats['total_lines'] += $lines;

            $mtime = filemtime($fullPath);
            $day = date('Y-m-d', $mtime);
            $stats['activity_by_day'][$day] = ($stats['activity_by_day'][$day] ?? 0) + 1;

            $stats['file_details'][] = [
                'path' => $relativePath,
                'size' => $size,
                'lines' => $lines,
                'created' => filectime($fullPath),
                'modified' => $mtime,
                'type' => $ext,
            ];

            $largest[] = ['path' => $relativePath, 'size' => $size, 'lines' => $lines];
        }

        usort($largest, fn($a, $b) => $b['size'] <=> $a['size']);
        $stats['largest_files'] = array_slice($largest, 0, 10);

        ksort($stats['activity_by_day']);

        return $stats;
    }

    /**
     * Подсчёт строк кода (исключая пустые строки и комментарии)
     */
    private function countLines(string $file, string $ext): int
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return 0;
        }

        // Удаляем многострочные комментарии /* ... */
        $content = preg_replace('/\/\*.*?\*\//s', '', $content);

        // Удаляем однострочные комментарии // и # (но не внутри строк)
        $lines = explode("\n", $content);
        $count = 0;
        foreach ($lines as $line) {
            $line = preg_replace('/\/\/.*$|#.*$/', '', $line);
            $line = trim($line);
            if ($line !== '') {
                $count++;
            }
        }
        return $count;
    }
}