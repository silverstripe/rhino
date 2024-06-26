<?php

namespace App\DataFetcher\Requesters;

use App\DataFetcher\Apis\ApiConfigInterface;
use App\DataFetcher\Models\ApiData;
use App\DataFetcher\Misc\Logger;
use App\DataFetcher\Interfaces\TypeInterface;
use LogicException;
use stdClass;

abstract class AbstractRequester implements TypeInterface
{
    protected $apiConfig;

    private $lastRequestTS = 0;

    public function __construct(ApiConfigInterface $apiConfig)
    {
        $this->apiConfig = $apiConfig;
    }

    public function fetch(
        string $path,
        string $postBody,
        string $account,
        string $repo,
        bool $refetch
    ): stdClass {
        $postBodyHash = md5($postBody);
        $data = [
            'Api' => $this->apiConfig->getType(),
            'Requester' => $this->getType(),
            'Path' => $path,
            'PostBodyHash' => $postBodyHash,
            'Account' => $account,
            'Repo' => $repo,
        ];

        $apiData = ApiData::get()->filter($data)->first();
        $ucType = strtoupper($this->getType());
        $ucMethod = strtoupper($this->getMethod($postBody));
        $url = $this->apiConfig->deriveUrl($path);
        $hash = $postBodyHash == md5('') ? '' : $postBodyHash;
        $logStr = "{$ucType} {$ucMethod} {$url} {$hash}";

        if ($refetch && $apiData) {
            $apiData->delete();
        } elseif ($apiData) {
            $ucType = strtoupper($this->getType());
            Logger::singleton()->log("Using local data for {$logStr}");
            return $this->buildResponse($apiData);
        }

        Logger::singleton()->log("Fetching remote data for {$logStr}");
        $json = $this->fetchDataFromApi($path, $postBody);
        if (strtolower($json) === 'null') {
            $json = null;
        }

        $data['ResponseBody'] = $json;
        $apiData = ApiData::create()->update($data);
        $apiData->write();

        return $this->buildResponse($apiData);
    }

    public function fetchFile(
        string $account,
        string $repo,
        string $branch,
        string $filePath,
        bool $refetch
    ): mixed {
        $fetchUrl = "https://raw.githubusercontent.com/$account/$repo/$branch/$filePath";

        $data = [
            'Api' => $this->apiConfig->getType(),
            'Requester' => $this->getType(),
            'Path' => $fetchUrl,
            'PostBodyHash' => '',
            'Account' => $account,
            'Repo' => $repo,
        ];
        $apiData = ApiData::get()->filter($data)->first();

        if ($refetch && $apiData) {
            $apiData->delete();
        } elseif ($apiData) {
            Logger::singleton()->log("Using local data for {$fetchUrl}");
            return str_ends_with($fetchUrl, '.json') ? json_decode($apiData->ResponseBody) : $apiData->ResponseBody;
        }

        Logger::singleton()->log("Fetching remote data for {$fetchUrl}");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fetchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseCode !== 200) {
            if ($responseCode !== 404) {
                Logger::singleton()->log("Error fetching file: $responseCode");
            }
            return null;
        }

        $data['ResponseBody'] = $result;
        $apiData = ApiData::create()->update($data);
        $apiData->write();
        return str_ends_with($fetchUrl, '.json') ? json_decode($result) : $result;
    }

    abstract protected function fetchDataFromApi(string $path, string $postBody = ''): string;

    protected function getMethod(string $postBody): string
    {
        return $postBody ? 'post' : 'get';
    }

    protected function waitUntilCanFetch()
    {
        // https://developer.github.com/v3/#rate-limiting
        // - authentacted users can make 5,000 requests per hour
        // - wait 1 second between requests (max of 3,600 per hour)
        $ts = time();
        if ($ts == $this->lastRequestTS) {
            sleep(1);
        }
        $this->lastRequestTS = $ts;
    }

    private function buildResponse(ApiData $apiData): stdClass
    {
        // ResponseBody may be a json array, json object or null, so add a root node
        // so that fetch can have a return type of stdClass
        // using the key name "root" instead of "data" because "data" is used by some API's
        // and it looks weird to have $json->data->data
        $root = is_null($apiData->ResponseBody) ? 'null' : $apiData->ResponseBody;
        if ($root === '') {
            $root = '""';
        }
        $response = json_decode('{"root":'.$root.'}');
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new LogicException(json_last_error_msg());
        }
        return $response;
    }
}
