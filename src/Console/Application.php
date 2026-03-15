<?php
namespace Scancore\Console;

class Application
{
    private array $commands = [];

    public function __construct()
    {
        $this->commands['tree']     = new TreeCommand();
        $this->commands['dump']     = new DumpCommand();
        $this->commands['stats']    = new StatsCommand();
        $this->commands['init']     = new InitCommand();
    }

    public function run(): void
    {
        global $argv;
        array_shift($argv);
        $commandName = $argv[0] ?? 'tree';
        $args = array_slice($argv, 1);

        if (!isset($this->commands[$commandName])) {
            $this->error("Unknown command: $commandName");
            return;
        }

        try {
            $this->commands[$commandName]->execute($args);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    private function error(string $message): void
    {
        fwrite(STDERR, "Error: $message\n");
        exit(1);
    }
}