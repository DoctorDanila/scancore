<?php
namespace Scancore\Console;

interface ICommand
{
    public function execute(array $args): void;
}