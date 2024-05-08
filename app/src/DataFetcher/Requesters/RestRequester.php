<?php

namespace App\DataFetcher\Requesters;

use App\DataFetcher\Misc\Logger;

class RestRequester extends AbstractRequester
{
    public function getType(): string
    {
        return 'rest';
    }

    protected function fetchDataFromApi(string $path, string $postBody = ''): string
    {
        $method = $this->getMethod($postBody);
        $ucMethod = strtoupper($method);
        $apiConfig = $this->apiConfig;
        $supportsPagination = $apiConfig->supportsPagination($path);
        $initial = $apiConfig->getPaginationOffsetInitial();
        $increment = $apiConfig->getPaginationOffsetIncrement();
        $maximum = $supportsPagination ? $apiConfig->getPaginationOffsetMaximum() : $initial;
        $results = [];
        $lastKey = '';
        $lastValue = '';
        for ($offset = $initial; $offset <= $maximum; $offset = $offset + $increment) {
            $ch = curl_init();
            $url = $apiConfig->deriveUrl($path, $offset);
            Logger::singleton()->log("REST {$ucMethod} {$url}");
            curl_setopt($ch, CURLOPT_URL, $url);
            if (strtolower($method) == 'post') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $apiConfig->getHeaders($this, $method));
            foreach ($apiConfig->getCurlOptions() as $curlOpt => $value) {
                curl_setopt($ch, $curlOpt, $value);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $this->waitUntilCanFetch();
            $s = curl_exec($ch);
            curl_close($ch);
            $json = json_decode($s);
            if (!is_array($json) && !is_object($json)) {
                Logger::singleton()->log('Error fetching data');
                return '';
            }
            if (empty($json) || !$json) {
                break;
            }
            // detect duplicate results e.g. /branches?per_page=100&page=1 ... /branches?per_page=100&page=2
            if (is_array($json)) {
                $keys = array_keys($json);
                if (!empty($keys)) {
                    $currentLastKey = $keys[count($keys) - 1];
                    $currentLastValue = $json[$currentLastKey];
                    if ($lastKey == $currentLastKey && $lastValue == $currentLastValue) {
                        break;
                    }
                    $lastKey = $currentLastKey;
                    $lastValue = $currentLastValue;
                }
            }
            $results[] = $json;
        }
        if (!$supportsPagination) {
            return json_encode($results[0], JSON_PRETTY_PRINT);
        }
        $arr = [];
        foreach ($results as $result) {
            // Example of non array is github {'message' => 'Not Found'}
            // such as when requesting from paginatable url of a non-existant branch
            if (!is_array($result)) {
                continue;
            }
            $arr = array_merge($arr, $result);
        }
        return json_encode($arr, JSON_PRETTY_PRINT);
    }
}
