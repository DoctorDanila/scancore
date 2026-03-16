<?php
namespace Scancore\Console;

use Scancore\Scanner;
use Scancore\TreeBuilder;
use Scancore\TreeRenderer;
use Scancore\IgnoreOptionHandler;

class TreeCommand implements ICommand
{
    public function execute(Input $input): void
    {
        $args = $input->getArguments();
        $path = isset($args[0]) ? trim(str_replace('\\', '/', $args[0]), '/') : '';
        $root = getcwd();

        $ignoreHandler = new IgnoreOptionHandler($input, 'tree', $root);
        $additionalPatterns = $ignoreHandler->getPatterns();

        $scanner = new Scanner($root, $additionalPatterns);
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