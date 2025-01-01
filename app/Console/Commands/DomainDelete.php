<?php

namespace App\Console\Commands;

use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use Illuminate\Console\Command;

class DomainDelete extends Command
{
    protected $signature = 'domain:del';
    protected $description = 'Delete local domain list';

    public function handle()
    {
        $hosts = $this->hosts_list();

        $domains = array_map(function ($host) {
            return $host['host'];
        }, $hosts);

        if (empty($domains)) {
            error('No domains found to delete.');
            return;
        }

        $select = select("Select domain: ", $domains);

        if (!$select) {
            warning('No domain selected.');
            return;
        }

        $key = array_search($select, $domains);

        unset($hosts[$key]);

        info($select . ' domain will be deleted');

        $confirm = confirm('Are you sure?', true);

        if ($confirm) {
            $this->update_hosts($hosts);
            info('Hosts was updated!');

            $this->disable_apache_conf($select);
            info('Apache conf was disabled!');

            $this->delete_apache_conf($select);
            info('Apache conf was deleted!');

            $this->reload_apache();
            info('Apache was reloaded!');

            info('Domain has been successfully deleted.');
        } else {
            error('Saving changes was aborted');
        }
    }

    public function hosts_list()
    {
        $hosts = file_get_contents('/etc/hosts');
        $ex = explode("\n", $hosts);

        $hosts = [];

        foreach ($ex as $host) {
            if (empty($host)) {
                continue;
            }

            if (str_starts_with($host, '#')) {
                break;
            }

            $ex = explode('	', $host);
            $hosts[] = [
                'ip' => $ex[0],
                'host' => $ex[1],
            ];
        }

        return $hosts;
    }

    public function hosts($hosts)
    {
        $hosts = implode("\n", array_map(function ($host) {
            return $host['ip'] . '	' . $host['host'];
        }, $hosts));

        $plain = file_get_contents(storage_path('domain/hosts.txt'));
        $data = str_replace('[*DOMAINS FOR SAVE*]', $hosts, $plain);

        return $data;
    }

    public function update_hosts($hosts)
    {
        $data = $this->hosts($hosts);

        return exec("echo " . escapeshellarg($data) . " | sudo tee /etc/hosts > /dev/null");
    }

    public function disable_apache_conf($domain)
    {
        return exec("sudo a2dissite " . $domain);
    }

    public function delete_apache_conf($domain)
    {
        return exec("sudo rm /etc/apache2/sites-available/" . $domain . ".conf");
    }

    public function reload_apache()
    {
        return exec("sudo systemctl reload apache2");
    }
}