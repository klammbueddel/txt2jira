<?php

namespace App;

use RuntimeException;

class Config
{

    public string $host = '';
    public string $user = '';
    public string $token = '';
    public string $file = '~/txt2jira.log';
    public string $jiraCache = '~/.txt2jira.cache';
    public int $roundMinutes = 5;
    public string $dateRegex = '/^([0-9]{2}\.[0-9]{2}\.[0-9]{4}).*/';
    private string $configFile;

    /**
     * @return string
     */
    public function getJiraCache(): string
    {
        return $this->resolve($this->jiraCache);
    }

    /**
     * @return string
     */
    public function getFile(): string
    {
        return $this->resolve($this->file);
    }

    public function resolve($path) {
        return str_replace('~', getenv("HOME"), $path);
    }

    public function save($path = null): void
    {
        $path = $this->resolve($path ?: $this->configFile);

        file_put_contents(
            $path,
            json_encode([
                'host' => $this->host,
                'user' => $this->user,
                'token' => $this->token,
                'file' => $this->file,
                'roundMinutes' => $this->roundMinutes,
                'dateRegex' => $this->dateRegex,
            ])
        );
    }

    public function load($path): void
    {
        $path = $this->resolve($path);
        $this->configFile = $path;
        if (!file_exists($path)) {
            throw new RuntimeException("Config file $path not found. Run setup with `txt2jira config`");
        }

        $config = json_decode(file_get_contents($path), true);

        $this->host = $config['host'] ?? null;
        $this->user = $config['user'] ?? null;
        $this->token = $config['token'] ?? null;
        $this->file = $config['file'] ?? $this->file;
        $this->roundMinutes = $config['roundMinutes'] ?? $this->roundMinutes;
        $this->dateRegex = $config['dateRegex'] ?? $this->dateRegex;
    }
}
