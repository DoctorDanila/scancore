<?php
namespace Scancore\Console;

use Scancore\Scanner;
use Scancore\TreeBuilder;
use Scancore\TreeRenderer;

class TreeCommand implements ICommand
{
    public function execute(array $args): void
    {
        $path = isset($args[0]) ? trim(str_replace('\\', '/', $args[0]), '/') : '';
        $root = getcwd();

        $scanner = new Scanner($root);
        $paths = $scanner->scan();

        $builder = new TreeBuilder();
        $tree = $builder->buildTree($paths, $path ?: null);

        $renderer = new TreeRenderer();

        if ($path !== '') {
            echo $path . '/' . PHP_EOL;
        }

        echo $renderer->render($tree);
    }
}