<?php

namespace App;

class Importer
{
    private $cache;

    public function __construct(
        private readonly JiraClient $client,
        private readonly Config $config,
    ) {
        if (file_exists($this->config->getJiraCache())) {
            $this->cache = json_decode(file_get_contents($this->config->getJiraCache()), true) ?: [];
        } else {
            $this->cache = [];
        }
    }

    public function update(array $keys)
    {
        if (!$keys) {
            return;
        }

        $keys = array_map('strtoupper', $keys);
        try {
            $issues = $this->client->getIssues($keys);
        } catch (HttpException $ex) {
            $json = json_decode($ex->getMessage(), true);
            $error = $json['errorMessages'][0] ?? $ex->getMessage();

            // Jira error if issue is invalid {"errorMessages":["The issue key 'FOO' for field 'key' is invalid."],"warningMessages":[]}
            if (preg_match('/.*The issue key \'(.*)\' for field \'key\' is invalid.*/', $error, $matches)) {

                $this->cache[$matches[1]] = [];
                $this->saveCache();

                $keys = array_diff($keys, [$matches[1]]);

                return $this->update($keys);
            } else {
                throw $ex;
            }
        }

        foreach ($issues as $issue) {
            $this->cache[$issue['key']] = $issue;
        }

        $this->saveCache();
    }

    public function saveCache()
    {
        file_put_contents($this->config->getJiraCache(), json_encode($this->cache));
    }

    public function resolveIssue($key)
    {
        $key = strtoupper($key);
        $issue = $this->cache[$key] ?? null;
        if ($issue === null) {
            $this->update([$key]);
        }

        return $this->cache[$key] ?? null;
    }

    public function getSummary($key)
    {
        $issue = $this->resolveIssue($key);

        return $issue['fields']['summary'] ?? null;
    }

}
