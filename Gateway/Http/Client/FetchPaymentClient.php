<?php
/**
 * Copyright © MercadoPago. All rights reserved.
 *
 * @author      Mercado Pago
 * @license     See LICENSE for license details.
 */

namespace MercadoPago\AdbPayment\Gateway\Http\Client;

use Exception;
use InvalidArgumentException;
use Magento\Framework\HTTP\ZendClient;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use MercadoPago\AdbPayment\Gateway\Config\Config;

/**
 * Communication with the Gateway to seek Payment information.
 */
class FetchPaymentClient implements ClientInterface
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
     * Store Id - Block name.
     */
    public const NOTIFICATION_ID = 'notificationId';

    /**
     * Mercado Pago Payment Id - Block Name.
     */
    public const MP_PAYMENT_ID = 'mp_payment_id';

    /**
     * Response Payment Id - Block name.
     */
    public const RESPONSE_PAYMENT_ID = 'id';

     /**
     * Response Payment Id - Block name.
     */
    public const RESPONSE_NOTIFICATION_ID = 'notification_id';

     /**
     * Response Payment Id - Block name.
     */
    public const RESPONSE_TRANSACTION_ID = 'transaction_id';

    /**
     * Response Pay Status - Block Name.
     */
    public const RESPONSE_STATUS = 'status';

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
        $url = $this->config->getApiUrl();
        $clientConfigs = $this->config->getClientConfigs();
        $clientHeaders = $this->config->getClientHeaders($storeId);
        $paymentId = $request[self::MP_PAYMENT_ID];
        $notificationId = $request[self::NOTIFICATION_ID];

        try {
            $client->setUri($url.'/v1/asgard/notification/'.$notificationId);
            $client->setConfig($clientConfigs);
            $client->setHeaders($clientHeaders);
            $client->setMethod(ZendClient::GET);
            $responseBody = $client->request()->getBody();
            $data = $this->json->unserialize($responseBody);
            $response = array_merge(
                [
                    self::RESULT_CODE  => 0,
                ],
                $data
            );
            if (isset($data[self::RESPONSE_TRANSACTION_ID])) {
                $response = array_merge(
                    [
                        self::RESULT_CODE          => 1,
                        self::RESPONSE_PAYMENT_ID  => $data[self::RESPONSE_TRANSACTION_ID],
                    ],
                    $data
                );
            }
            $this->logger->debug(
                [
                    'url'      => $url.'/v1/asgard/notification/'.$notificationId,
                    'response' => $this->json->serialize($data),
                ]
            );

        } catch (InvalidArgumentException $exc) {
            $this->logger->debug(
                [
                    'url'       => $url.'/v1/asgard/notification/'.$notificationId,
                    'response'  => $this->json->serialize($transferObject->getBody()),
                    'error'     => $exc->getMessage(),
                ]
            );
            // phpcs:ignore Magento2.Exceptions.DirectThrow
            throw new Exception('Invalid JSON was returned by the gateway');
        }

        return $response;
    }
}
