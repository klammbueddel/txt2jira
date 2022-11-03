<?php

namespace App;


class Config
{

    public string $host = '';
    public string $user = '';
    public string $token = '';
    public string $file = '';

    public function __construct(private readonly string $configFile = __DIR__."/../config.json")
    {
        $this->load();
    }

    public function save(): void
    {
        file_put_contents(
            $this->configFile,
            json_encode([
                'host' => $this->host,
                'user' => $this->user,
                'token' => $this->token,
                'file' => $this->file,
            ])
        );
    }

    public function load(): void
    {
        if (!file_exists($this->configFile)) {
            return;
        }

        $config = json_decode(file_get_contents($this->configFile), true);

        $this->host = $config['host'] ?? null;
        $this->user = $config['user'] ?? null;
        $this->token = $config['token'] ?? null;
        $this->file = $config['file'] ?? null;
    }
}
