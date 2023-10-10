<?php

namespace App\DataFetcher\Requesters;

use App\DataFetcher\Misc\Logger;

class GraphQLRequester extends AbstractRequester
{
    public function getType(): string
    {
        return 'graphql';
    }

    protected function fetchDataFromApi(string $path, string $postBody = ''): string
    {
        $method = $this->getMethod($postBody);
        $ucMethod = strtoupper($method);
        $apiConfig = $this->apiConfig;

        $url = $apiConfig->deriveUrl($path);
        Logger::singleton()->log("REST {$ucMethod} {$url}");
        $queryJson = $this->buildGraphQLQueryJson($postBody);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryJson);
        curl_setopt($ch, CURLOPT_URL, $apiConfig->deriveUrl($path));
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
        // making assumption that we're using the search "endpoint"
        if (!isset($json->data->search->nodes)) {
            Logger::singleton()->log('Error fetching data');
            return '';
        }
        return json_encode($json, JSON_PRETTY_PRINT);
    }

    private function buildGraphQLQueryJson(string $postBody)
    {
        $q = trim($postBody);
        $q = str_replace("\n", '', $q);
        $q = preg_replace('/ {2,}/', ' ', $q);
        $q = str_replace('"', '\\"', $q);
        return "{\"query\":\"$q\"}";
    }
}
