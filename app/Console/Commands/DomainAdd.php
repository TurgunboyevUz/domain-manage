<?php

namespace App\Console\Commands;

use function Laravel\Prompts\info;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use Illuminate\Console\Command;

class DomainAdd extends Command
{
    protected $signature = 'domain:add';
    protected $description = 'Add new domain to localhost';

    public function handle()
    {
        $data = $this->data();

        $path = $data['path'];
        $domain = $data['domain'];
        $base_path = $data['base_path'];

        if ($this->host_exists($domain)) {
            error('Domain already exists');
            return;
        }

        $save = confirm("Save changes?", true);

        if ($save) {
            $this->update_hosts($domain);
            info('Hosts was updated!');

            $this->create_apache_conf($domain, $path, $base_path);
            info('Apache conf was created!');

            $this->enable_apache_conf($domain);
            info('Apache conf was enabled!');

            $this->reload_apache();
            info('Apache was reloaded!');

            $this->access_www_data($base_path);
            info('Given path www-data access!');

            info('Changes was saved');
        } else {
            error('Cancelled adding domain');
        }
    }

    public function data()
    {
        $domain_type = select("Select domain type", [
            'test' => 'Test',
            'custom' => 'Custom',
        ]);

        $path_type = select("Select path type", [
            'laravel' => 'Laravel',
            'non-laravel' => 'Non-Laravel',
            'custom' => 'Custom',
        ]);

        $domain = text("Domain name: ", "Ex: codearch.uz", required: true);

        if ($domain_type == 'test') {
            $domain_folder = $domain;
            $domain = $domain . '.local.test';
        }else{
            $domain_folder = text("Domain folder: ", "Ex: codearch.uz", required: true);
        }

        $base_path = null;

        if ($path_type == 'laravel') {
            $path = '/var/www/html/projects/' . $domain_folder . '/public';
            $base_path = '/var/www/html/projects/' . $domain_folder;
        }

        if ($path_type == 'non-laravel') {
            $path = '/var/www/html/projects/' . $domain_folder;
        }

        if ($path_type == 'custom') {
            $path = text("Path: ", "Ex: /var/www/html/codearch.uz/", required: true);
        }

        return [
            'domain' => $domain,
            'path' => $path,
            'base_path' => $base_path ?? $path,
        ];
    }

    public function host_list()
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

    public function host_exists($domain)
    {
        $hosts = $this->host_list();

        foreach ($hosts as $host) {
            if ($host['host'] == $domain) {
                return true;
            }
        }

        return false;
    }

    public function hosts($domain)
    {
        $hosts = $this->host_list();

        $hosts[] = [
            'ip' => '127.0.0.1',
            'host' => $domain,
        ];

        $hosts = implode("\n", array_map(function ($host) {
            return $host['ip'] . '	' . $host['host'];
        }, $hosts));

        $plain = file_get_contents(storage_path('domain/hosts.txt'));
        $data = str_replace('[*DOMAINS FOR SAVE*]', $hosts, $plain);

        return $data;
    }

    public function conf($domain, $path, $base_path)
    {
        $plain = file_get_contents(storage_path('domain/apache.conf.txt'));

        $data = str_replace([
            '[*DOMAIN*]',
            '[*ROOT_PATH*]',
            '[*BASE_PATH*]',
        ], [
            $domain,
            $path,
            $base_path
        ], $plain);

        return $data;
    }

    public function update_hosts($domain)
    {
        $data = $this->hosts($domain);

        return exec("echo " . escapeshellarg($data) . " | sudo tee /etc/hosts > /dev/null");
    }

    public function create_apache_conf($domain, $path, $base_path)
    {
        $conf = $this->conf($domain, $path, $base_path);

        return exec("echo " . escapeshellarg($conf) . " | sudo tee /etc/apache2/sites-available/" . $domain . ".conf > /dev/null");
    }

    public function enable_apache_conf($domain)
    {
        return exec("sudo a2ensite " . $domain . ".conf");
    }

    public function reload_apache()
    {
        return exec("sudo systemctl reload apache2");
    }

    public function access_www_data($path)
    {
        return exec("sudo chown -R www-data:www-data " . $path);
    }
}
