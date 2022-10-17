<?php

namespace App;

use DateTime;
use Exception;

class JiraClient
{

    public function __construct(private readonly Config $config) { }

    private function prepare($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://'.$this->config->host.$url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
        curl_setopt($ch, CURLOPT_USERPWD, $this->config->user.':'.$this->config->token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); //timeout in seconds

        return $ch;
    }

    private function request($ch)
    {
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);

        if (!$info['http_code']) {
            throw new Exception(curl_error($ch));
        }

        if ($info['http_code'] >= 400) {
            throw new Exception('HTTP '.$info['http_code'].': '.$response);
        }

        return $response;
    }

    private function get($url)
    {
        return $this->request($this->prepare($url));
    }

    private function put($url, $data)
    {
        $ch = $this->prepare($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        return $this->request($ch);
    }

    private function post($url, $data)
    {
        $ch = $this->prepare($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        return $this->request($ch);
    }

    public function addWorkLog(string $issue, string $comment, DateTime $started, string $timeSpent)
    {
        return $this->post(
            '/rest/api/2/issue/'.$issue.'/worklog',
            [
                "comment" => $comment,
                "started" => $started->format('Y-m-d\TH:i:s.vO'),
                "timeSpent" => $timeSpent,
            ]
        );
    }

    public function getCurrentUser()
    {
        return $this->get('/rest/api/2/myself');
    }
}
