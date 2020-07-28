<?php

declare(strict_types=1);

namespace Prometheus;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class PushGateway
{
    /**
     * @var string
     */
    private $address;
    private $connect_timeout;
    private $timeout;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * PushGateway constructor.
     * @param $address string host:port of the push gateway
     * @param $connect_timeout int HTTP connection timeout
     * @param $timeout int HTTP request timeout
     * @param ClientInterface $client
     */
    public function __construct(string $address, int $connect_timeout = 10, int $timeout = 20, ClientInterface $client = null)
    {
        $this->address = $address;
        $this->connect_timeout = $connect_timeout;
        $this->timeout = $timeout;
        $this->client = $client ?? new Client();
    }

    /**
     * Pushes all metrics in a Collector, replacing all those with the same job.
     * Uses HTTP PUT.
     * @param CollectorRegistry $collectorRegistry
     * @param string $job
     * @param array $groupingKey
     * @throws GuzzleException
     */
    public function push(CollectorRegistry $collectorRegistry, string $job, array $groupingKey = []): void
    {
        $this->doRequest($collectorRegistry, $job, $groupingKey, 'put');
    }

    /**
     * Pushes all metrics in a Collector, replacing only previously pushed metrics of the same name and job.
     * Uses HTTP POST.
     * @param CollectorRegistry $collectorRegistry
     * @param $job
     * @param $groupingKey
     * @throws GuzzleException
     */
    public function pushAdd(CollectorRegistry $collectorRegistry, string $job, array $groupingKey = []): void
    {
        $this->doRequest($collectorRegistry, $job, $groupingKey, 'post');
    }

    /**
     * Deletes metrics from the Push Gateway.
     * Uses HTTP POST.
     * @param string $job
     * @param array $groupingKey
     * @throws GuzzleException
     */
    public function delete(string $job, array $groupingKey = []): void
    {
        $this->doRequest(null, $job, $groupingKey, 'delete');
    }

    /**
     * @param CollectorRegistry $collectorRegistry
     * @param string $job
     * @param array $groupingKey
     * @param string $method
     * @throws GuzzleException
     */
    private function doRequest(CollectorRegistry $collectorRegistry, string $job, array $groupingKey, $method): void
    {
        $url = "http://" . $this->address . "/metrics/job/" . $job;
        if (!empty($groupingKey)) {
            foreach ($groupingKey as $label => $value) {
                $url .= "/" . $label . "/" . $value;
            }
        }

        $requestOptions = [
            'headers' => [
                'Content-Type' => RenderTextFormat::MIME_TYPE,
            ],
            'connect_timeout' => $this->connect_timeout,
            'timeout' => $this->timeout,
        ];

        if ($method != 'delete') {
            $renderer = new RenderTextFormat();
            $requestOptions['body'] = $renderer->render($collectorRegistry->getMetricFamilySamples());
        }
        $response = $this->client->request($method, $url, $requestOptions);
        $statusCode = $response->getStatusCode();
        if (!in_array($statusCode, [200, 202])) {
            $msg = "Unexpected status code "
                . $statusCode
                . " received from push gateway "
                . $this->address . ": " . $response->getBody();
            throw new RuntimeException($msg);
        }
    }
}
