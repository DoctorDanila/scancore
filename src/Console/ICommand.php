<?php
namespace Scancore\Console;

interface ICommand
{
    public function execute(Input $input): void;
}