<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Domain extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Local domain list';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hosts = file_get_contents('/etc/hosts');
        $ex = explode("\n", $hosts);

        $hosts = [];

        foreach ($ex as $host) {
            if(str_starts_with($host, '#')) {
                break;
            }

            if(empty($host)) {
                continue;
            }

            $ex = explode('	', $host);
            $hosts[] = [
                'ip' => $ex[0],
                'host' => $ex[1],
            ];
        }

        $this->table(['IP', 'Host'], $hosts);
    }
}
