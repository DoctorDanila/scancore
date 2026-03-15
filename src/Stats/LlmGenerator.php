<?php
namespace Scancore\Stats;

class LlmGenerator
{
    private array $stats;
    private array $dependencies;

    public function __construct(array $stats, array $dependencies)
    {
        $this->stats = $stats;
        $this->dependencies = $dependencies;
    }

    public function generate(): string
    {
        $output = [
            'summary' => [
                'total_files' => $this->stats['total_files'],
                'total_lines_of_code' => $this->stats['total_lines'],
                'files_by_type' => $this->stats['files_by_type'],
            ],
            'largest_files' => array_map(function($f) {
                return ['path' => $f['path'], 'size_bytes' => $f['size'], 'lines' => $f['lines']];
            }, $this->stats['largest_files']),
            'most_depended_upon' => [],
            'most_depending' => [],
        ];

        $i = 0;
        foreach ($this->dependencies['dependencies'] ?? [] as $file => $deps) {
            if ($i++ >= 20) break;
            $output['most_depended_upon'][] = [
                'file' => $file,
                'depended_by_count' => count($deps),
                'depended_by' => $deps,
            ];
        }

        $dependingCounts = [];
        foreach ($this->dependencies['dependents'] ?? [] as $file => $dependsOn) {
            $dependingCounts[$file] = count($dependsOn);
        }
        arsort($dependingCounts);
        $i = 0;
        foreach ($dependingCounts as $file => $count) {
            if ($i++ >= 20) break;
            $output['most_depending'][] = [
                'file' => $file,
                'depends_on_count' => $count,
                'depends_on' => $this->dependencies['dependents'][$file],
            ];
        }

        $output['dependencies_graph'] = $this->dependencies;

        return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}