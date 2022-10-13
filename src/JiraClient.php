<?php

namespace App;

use DateTime;
use Exception;

class JiraClient
{

    public function __construct(private $config) { }

    private function prepare($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['host'].$url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
        curl_setopt($ch, CURLOPT_USERPWD, $this->config['user'].':'.$this->config['token']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); //timeout in seconds

        return $ch;
    }

    private function get($url)
    {
        $ch = $this->prepare($url);

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);

        if (!$info['http_code']) {
            throw new Exception(curl_error($ch).var_export($info, true));
        }

        http_response_code($info['http_code']);
        header("Content-Type: ".$info['content_type']);

        return $response;
    }

    private function put($url, $data)
    {
        $ch = $this->prepare($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);

        if (!$info['http_code']) {
            throw new Exception(curl_error($ch));
        }

        http_response_code($info['http_code']);
        header("Content-Type: ".$info['content_type']);

        return $response;
    }

    private function post($url, $data)
    {
        $ch = $this->prepare($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);

        if (!$info['http_code']) {
            throw new Exception(curl_error($ch));
        }

        if ($info['http_code'] >= 400) {
            throw new Exception($response);
        }

        return $response;
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
}
