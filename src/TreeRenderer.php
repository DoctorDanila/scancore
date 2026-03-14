<?php
namespace Scancore;

class TreeRenderer
{
    public function render(array $tree, string $prefix = ''): string
    {
        $output = '';
        $i = 0;
        $total = count($tree);
        foreach ($tree as $name => $subtree) {
            $i++;
            $isLast = ($i === $total);
            $connector = $isLast ? '└─ ' : '├─ ';
            $output .= $prefix . $connector . $name . PHP_EOL;

            if (is_array($subtree)) {
                $newPrefix = $prefix . ($isLast ? '    ' : '│   ');
                $output .= $this->render($subtree, $newPrefix);
            }
        }
        return $output;
    }
}