<?php
/**
 * Copyright © MercadoPago. All rights reserved.
 *
 * @author      Bruno Elisei <brunoelisei@o2ti.com>
 * @license     See LICENSE for license details.
 */

namespace MercadoPago\PaymentMagento\Gateway\Http\Client;

use Exception;
use InvalidArgumentException;
use Magento\Framework\HTTP\ZendClient;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use MercadoPago\PaymentMagento\Gateway\Config\Config;

/**
 * Communication with the Gateway to create a payment by custom (Card, Pix, Ticket, Pec).
 */
class CreateOrderPaymentCustomClient implements ClientInterface
{
    /**
     * Result Code - Block name.
     */
    public const RESULT_CODE = 'RESULT_CODE';

    /**
     * Store Id - Block name.
     */
    public const STORE_ID = 'store_id';

    /**
     * External Order Id - Block name.
     */
    public const EXT_ORD_ID = 'EXT_ORD_ID';

    /**
     * External Status - Block name.
     */
    public const STATUS = 'status';

    /**
     * External Status Rejected - Block name.
     */
    public const STATUS_REJECTED = 'rejected';

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ZendClientFactory
     */
    protected $httpClientFactory;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @param Logger            $logger
     * @param ZendClientFactory $httpClientFactory
     * @param Config            $config
     * @param Json              $json
     */
    public function __construct(
        Logger $logger,
        ZendClientFactory $httpClientFactory,
        Config $config,
        Json $json
    ) {
        $this->config = $config;
        $this->httpClientFactory = $httpClientFactory;
        $this->logger = $logger;
        $this->json = $json;
    }

    /**
     * Places request to gateway.
     *
     * @param TransferInterface $transferObject
     *
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        /** @var ZendClient $client */
        $client = $this->httpClientFactory->create();
        $request = $transferObject->getBody();
        $storeId = $request[self::STORE_ID];
        unset($request[self::STORE_ID]);

        $serializeResquest = $this->json->serialize($request);
        $url = $this->config->getApiUrl();
        $clientConfigs = $this->config->getClientConfigs();
        $clientHeaders = $this->config->getClientHeaders($storeId);

        try {
            $client->setUri($url.'/alpha/asgard/payments');
            $client->setConfig($clientConfigs);
            $client->setHeaders($clientHeaders);
            $client->setRawData($serializeResquest, 'application/json');
            $client->setMethod(ZendClient::POST);

            $responseBody = $client->request()->getBody();
            $data = $this->json->unserialize($responseBody);

            if ($data[self::STATUS] === self::STATUS_REJECTED) {
                $data['id'] = null;
            }

            $response = array_merge(
                [
                    self::RESULT_CODE  => isset($data['id']) ? 1 : 0,
                    self::EXT_ORD_ID   => isset($data['id']) ? $data['id'] : null,
                ],
                $data
            );
        } catch (InvalidArgumentException $exc) {
            // phpcs:ignore Magento2.Exceptions.DirectThrow
            throw new Exception('Invalid JSON was returned by the gateway');
        }

        unset($clientHeaders['Authorization']);
        $this->logger->debug(
            [
                'url'      => $url.'/alpha/asgard/payments',
                'header'   => $this->json->serialize($clientHeaders),
                'request'  => $serializeResquest,
                'response' => $this->json->serialize($responseBody),
            ]
        );

        return $response;
    }
}
