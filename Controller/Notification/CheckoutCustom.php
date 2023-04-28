<?php
/**
 * Copyright © MercadoPago. All rights reserved.
 *
 * @author      Mercado Pago
 * @license     See LICENSE for license details.
 */

namespace MercadoPago\AdbPayment\Controller\Notification;

use Exception;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\HTTP\ZendClient;
use MercadoPago\AdbPayment\Controller\MpIndex;

/**
 * Controler Notification Checkout Custom - Notification of receivers for Checkout Custom Methods.
 */
class CheckoutCustom extends MpIndex implements CsrfAwareActionInterface
{
    /**
     * Create Csrf Validation Exception.
     *
     * @param RequestInterface $request
     *
     * @return null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        if ($request) {
            return null;
        }
    }

    /**
     * Validate For Csrf.
     *
     * @param RequestInterface $request
     *
     * @return bool true
     */
    public function validateForCsrf(RequestInterface $request): bool
    {
        if ($request) {
            return true;
        }
    }

    /**
     * Execute.
     *
     * @return ResultInterface
     */
    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->createResult(
                404,
                [
                    'error'   => 404,
                    'message' => __('You should not be here...'),
                ]
            );
        }

        $mpAmountRefund = null;
        $response = $this->getRequest()->getContent();
        $mercadopagoData = $this->json->unserialize($response);
        $mpTransactionId = $mercadopagoData['transaction_id'];
        $mpStatus = $mercadopagoData['status'];
        $notificationId = $mercadopagoData['notification_id'];
        $paymentsDetails = $mercadopagoData['payments_details'];

        if ($mpStatus === 'refunded') {
            try {
                /** @var ZendClient $client */
                $client = $this->httpClientFactory->create();
                $storeId = $mercadopagoData["payments_metadata"]["store_id"];
                $url = $this->config->getApiUrl();
                $clientConfigs = $this->config->getClientConfigs();
                $clientHeaders = $this->config->getClientHeaders($storeId);
                
                $client->setUri($url.'/v1/asgard/notification/'.$notificationId);
                $client->setConfig($clientConfigs);
                $client->setHeaders($clientHeaders);
                $client->setMethod(ZendClient::GET);
                $responseBody = $client->request()->getBody();
                $respData = $this->json->unserialize($responseBody);
                if (
                    !empty($respData["multiple_payment_transaction_id"])
                ) {
                    $mpTransactionId = $respData["multiple_payment_transaction_id"];
                }

            } catch (Exception $e) {
                $this->logger->debug(['exception' => $e->getMessage()]);
            }
        }

        $this->logger->debug([
            'action'    => 'checkout_custom',
            'payload'   => $response
        ]);

        return $this->initProcess($mpTransactionId, $mpStatus, $notificationId, $paymentsDetails, $respData);
    }

    
    /**
     * Init Process.
     *
     * @param string $mpTransactionId
     * @param string $mpStatus
     * @param string $notificationId
     * @param $respData
     *
     * @return array ResultInterface
     */
    public function initProcess(
        $mpTransactionId,
        $mpStatus,
        $notificationId,
        $paymentsDetails,
        $respData = null
    ) {
        $searchCriteria = $this->searchCriteria->addFilter('txn_id', $mpTransactionId)
            ->create();

        try {
            /** @var TransactionRepositoryInterface $transactions */
            $transactions = $this->transaction->getList($searchCriteria)->getItems();
        } catch (Exception $exc) {

            /** @var ResultInterface $result */
            $result = $this->createResult(
                500,
                [
                    'error'   => 500,
                    'message' => $exc->getMessage(),
                ]
            );

            return $result;
        }

        $origin = '';
        $results = [];
        $mpAmountRefund = null;
        $process = [];

        foreach ($transactions as $transaction) {
            $order = $this->getOrderData($transaction->getOrderId());
            
            if ($mpStatus === 'refunded') {
                // alterar para verificar se é multipayment, se for, fazer o split da string usando o _
                foreach ($paymentsDetails as $paymentsDetail) {
                    $refunds = $paymentsDetail['refunds'];

                    foreach ($refunds as $refund) {
                        $this->logger->debug(['notifying' => $respData['refunds_notifying']]);

                        if (isset($refund['metadata']['origem'])){
                            $origin = $refund['metadata']['origem'];
                        }
                        if (
                            isset($respData['refunds_notifying'][$refund['id']]) 
                            && $respData['refunds_notifying'][$refund['id']]['notifying']
                        ) {
                            $mpAmountRefund = $respData['refunds_notifying'][$refund['id']]['amount'];

                            $process = $this->processNotification($mpStatus, $order, $notificationId, $mpAmountRefund, $origin);
                            
                            /** @var ResultInterface $result */
                            $result = $this->createResult(
                                $process['code'],
                                $process['msg'],
                            );
                            array_push($results, $result);
                        }
                    }
                }
            } else {
                $process = $this->processNotification($mpStatus, $order, $notificationId, $mpAmountRefund, $origin);
                
                /** @var ResultInterface $result */
                $result = $this->createResult(
                    $process['code'],
                    $process['msg'],
                );
                array_push($results, $result);
            }

            if (sizeof($results) === 0) {
                $result = $this->createResult(
                    'code' => 422,
                    'msg' => 'Nothing to proccess'
                );
                array_push($results, $result);
            }

            return $results;
        }

        /** @var ResultInterface $result */
        $result = $this->createResult(200, ['empty' => null]);

        return $result;
    }

    /**
     * Process Notification.
     *
     * @param string          $mpStatus
     * @param OrderRepository $order
     * @param string|null     $mpAmountRefund
     * @param string $notificationId
     *
     * @return array
     */
    public function processNotification(
        $mpStatus,
        $order,
        $notificationId,
        $mpAmountRefund = null,
        $origin = null
    ) {
        $result = [];

        $isNotApplicable = $this->filterInvalidNotification($mpStatus, $order, $mpAmountRefund, $origin);

        if ($isNotApplicable['isInvalid']) {
            if (
                strcmp($isNotApplicable['msg'], 'Refund notification for order refunded directly in Mercado Pago.') !== 0
                && strcmp($isNotApplicable['msg'], 'Refund notification for order already closed.') !== 0
                && strcmp($isNotApplicable['msg'], 'Notification response for online refund created in magento') !== 0
            ) {
                return $isNotApplicable;
            }
        }
        $order = $this->fetchStatus->fetch($order->getEntityId(), $notificationId);

        $result = [
            'code'  => 200,
            'msg'   => [
                'order'     => $order->getIncrementId(),
                'state'     => $order->getState(),
                'status'    => $order->getStatus(),
            ],
        ];

        return $result;
    }
}
