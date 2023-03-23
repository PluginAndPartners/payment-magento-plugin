<?php
/**
 * Copyright © MercadoPago. All rights reserved.
 *
 * @author      Bruno Elisei <brunoelisei@o2ti.com>
 * @license     See LICENSE for license details.
 */

namespace MercadoPago\PaymentMagento\Controller\Notification;

use Exception;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use MercadoPago\PaymentMagento\Controller\MpIndex;

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
        $txnType = 'authorization';
        $response = $this->getRequest()->getContent();
        $mercadopagoData = $this->json->unserialize($response);
        $mpTransactionId = $mercadopagoData['transaction_id'];
        $mpStatus = $mercadopagoData['status'];

        $this->logger->debug([
            'action'    => 'checkout_custom',
            'payload'   => $response,
            'transaction id' => $mpTransactionId,
        ]);

        if ($mpStatus === 'refunded') {
            $mpAmountRefund = $mercadopagoData['total_refunded'];
            $txnType = 'capture';
        }

        return $this->initProcess($txnType, $mpTransactionId, $mpStatus, $mpAmountRefund);
    }

    /**
     * Init Process.
     *
     * @param string $txnType
     * @param string $mpTransactionId
     * @param string $mpStatus
     * @param string $mpAmountRefund
     *
     * @return ResultInterface
     */
    public function initProcess(
        $txnType,
        $mpTransactionId,
        $mpStatus,
        $mpAmountRefund
    ) {
        $this->logger->debug([
            'Checkout type'    => 'Custom',
            'Action' => 'initProcess',
            'Mp payment status' => $mpStatus,
            'Transaction id' => $mpTransactionId,
            'tnx' => $txnType,
        ]);

        $searchCriteria = $this->searchCriteria->addFilter('txn_id', $mpTransactionId)
            ->addFilter('txn_type', $txnType)
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

        $this->logger->debug([
            'Checkout type'    => 'Custom',
            'Action'    => 'for each transaction',
        ]);

        foreach ($transactions as $transaction) {
            $order = $this->getOrderData($transaction->getOrderId());

            $this->logger->debug([
                'Checkout type'    => 'Custom',
                'Mp status' => $mpStatus,
                'Order status'    => $order->getStatus(),
                'Order id' => $transaction->getOrderId(),
            ]);

            $process = $this->processNotification($mpStatus, $order, $mpAmountRefund);

            $this->logger->debug([
                'Checkout type'    => 'Custom',
                'Action'    => 'After process notification',
                'code' => $process['code'],
                'msg' => $process['msg'],
                'order status' => $order->getStatus(),
                'order state' => $order->getState(),
            ]);

            /** @var ResultInterface $result */
            $result = $this->createResult(
                $process['code'],
                $process['msg'],
            );

            return $result;
        }

        $this->logger->debug([
            'Checkout type'    => 'Custom',
            'Action'    => 'No transactions',
        ]);

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
     *
     * @return array
     */
    public function processNotification(
        $mpStatus,
        $order,
        $mpAmountRefund = null
    ) {
        $this->logger->debug([
            'Checkout type'    => 'Custom',
            'Action'    => 'Process notification',
            'order status' => $order->getStatus(),
        ]);

        $result = [];

        $isNotApplicable = $this->filterInvalidNotification($mpStatus, $order, $mpAmountRefund, 'cho custom');

        if ($isNotApplicable['isInvalid']) {
            if ($isNotApplicable['code'] === 999) {
                $this->logger->debug([
                    'Checkout type'    => 'Custom',
                    'action'    => 'Refund for order not closed, proceeding',
                ]);
                
                $result = [
                    'isInvalid' => false,
                    'code'      => 200,
                    'msg'       => [
                        'error'   => 200,
                        'message' => __('Order not yet closed in Magento.'),
                        'state'   => $order->getState(),
                        'tatus'   => $order->getStatus(),
                    ],
                ];

            } else if ($isNotApplicable['code'] === 888) {
                $this->logger->debug([
                    'Checkout type'    => 'Custom',
                    'action'    => 'Refund for order already closed, proceeding',
                ]);
                
                $result = [
                    'isInvalid' => false,
                    'code'      => 200,
                    'msg'       => [
                        'error'   => 200,
                        'message' => __('Order already closed in Magento.'),
                        'state'   => $order->getState(),
                        'tatus'   => $order->getStatus(),
                    ],
                ];

            } else {
                return $isNotApplicable;
            }
        }

        $this->logger->debug([
            'Checkout type'    => 'Custom',
            'Notification validity'    => 'valid',
            'Action'    => 'Before fetch',
            'order'     => $order->getIncrementId(),
            'state'     => $order->getState(),
            'status'    => $order->getStatus(),
            'entity id' => $order->getEntityId(),
        ]);

        $order = $this->fetchStatus->fetch($order->getEntityId(), 'checkout custom');

        $this->logger->debug([
            'Class'     => 'CheckoutPro',
            'Action'    => 'After fetch',
            'order'     => $order->getIncrementId(),
            'state'     => $order->getState(),
            'status'    => $order->getStatus(),
            'entity id' => $order->getEntityId(),
        ]);

        $result = [
            'code'  => 200,
            'msg'   => [
                'order'     => $order->getIncrementId(),
                'state'     => $order->getState(),
                'status'    => $order->getStatus(),
            ],
        ];

        $this->logger->debug([
            'Checkout type'    => 'Custom',
            'Action'    => 'Notification processed',
            'msg'   => $result,
        ]);

        return $result;
    }
}
