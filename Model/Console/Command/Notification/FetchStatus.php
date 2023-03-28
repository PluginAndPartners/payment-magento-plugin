<?php
/**
 * Copyright © MercadoPago. All rights reserved.
 *
 * @author      Bruno Elisei <brunoelisei@o2ti.com>
 * @license     See LICENSE for license details.
 */

namespace MercadoPago\PaymentMagento\Model\Console\Command\Notification;

use Exception;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order;
use MercadoPago\PaymentMagento\Model\Console\Command\AbstractModel;

/**
 * Model for Command lines to capture Status on Mercado Pago.
 */
class FetchStatus extends AbstractModel
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Order
     */
    protected $order;

    /**
     * @param Logger $logger
     * @param Order  $order
     */
    public function __construct(
        Logger $logger,
        Order $order
    ) {
        parent::__construct(
            $logger
        );
        $this->order = $order;
    }

    /**
     * Command Fetch.
     *
     * @param int $orderId
     * @param string $caller
     *
     * @return Order $order
     */
    public function fetch($orderId, $caller)
    {
        $this->writeln('Init Fetch Status');
        /** @var Order $order */
        $order = $this->order->load($orderId);

        $this->logger->debug([
            'Caller class' => $caller,
            'Action'    => 'Fetching',
            'Order id' => $orderId,
        ]);

        // $this->logger->debug(['1' => '',]);
        $orderGetAppliedRuleIds = $order->getAppliedRuleIds() ?? 'null';
        // $this->logger->debug(['getAppliedRuleIds' => $orderGetAppliedRuleIds,]);
        $orderGetBaseSubtotal = $order->getBaseSubtotal() ?? 'null';
        // $this->logger->debug(['orderGetBaseSubtotal' => $orderGetBaseSubtotal,]);
        $orderGetExtOrderId = $order->getExtOrderId() ?? 'null';
        // $this->logger->debug(['orderGetExtOrderId' => $orderGetExtOrderId,]);
        $orderGetIncrementId = $order->getIncrementId() ?? 'null';
        // $this->logger->debug(['orderGetIncrementId' => $orderGetIncrementId,]);
        $orderGetProtectCode = $order->getProtectCode() ?? 'null';
        // $this->logger->debug(['orderGetProtectCode' => $orderGetProtectCode,]);
        $orderGetQuoteId = $order->getQuoteId() ?? 'null';
        // $this->logger->debug(['orderGetQuoteId' => $orderGetQuoteId,]);
        $orderGetStatus = $order->getStatus() ?? 'null';
        // $this->logger->debug(['orderGetStatus' => $orderGetStatus,]);
        $orderGetState = $order->getState() ?? 'null';
        // $this->logger->debug(['orderGetState' => $orderGetState,]);

        $this->logger->debug([
            'Order' => 'data:',
            'Order rule ids' => $orderGetAppliedRuleIds,
            'Order getBaseSubtotal' => $orderGetBaseSubtotal,
            'Order getExtOrderId' => $orderGetExtOrderId,
            'Order getIncrementId' => $orderGetIncrementId,
            'Order getProtectCode' => $orderGetProtectCode,
            'Order getQuoteId' => $orderGetQuoteId,
            'Order status' => $orderGetStatus,
            'Order getState' => $orderGetState,
        ]);
        
        $payment = $order->getPayment();
        
        // $this->logger->debug(['1' => '',]);
        $paymentgetAdditionalData = $payment->getAdditionalData() ?? 'null';
        // $this->logger->debug(['2' => $paymentgetAdditionalData,]);
        $paymentgetAdditionalInformation = $payment->getAdditionalInformation() ?? 'null';
        // $this->logger->debug(['3' => $paymentgetAdditionalInformation,]);
        $paymentgetCcType = $payment->getCcType() ?? 'null';
        // $this->logger->debug(['9' => $paymentgetCcType,]);
        $paymentgetEntityId = $payment->getEntityId() ?? 'null';
        // $this->logger->debug(['14' => $paymentgetEntityId,]);
        $paymentgetLastTransId = $payment->getLastTransId() ?? 'null';
        // $this->logger->debug(['15' => $paymentgetLastTransId,]);
        $paymentgetMethod = $payment->getMethod() ?? 'null';
        // $this->logger->debug(['16' => $paymentgetMethod,]);
        $paymentgetParentId = $payment->getParentId() ?? 'null';
        // $this->logger->debug(['14' => $paymentgetParentId,]);

        $this->logger->debug([
            'Caller class' => $caller,
            'Action'    => 'Payment data',
            'Order id' => $orderId,
            'Payment' => 'data:',
            'Payment getAdditionalData' => $payment->getAdditionalData(),
            'Payment getAdditionalInformation' => $payment->getAdditionalInformation(),
            'Payment getCcType' => $payment->getCcType(),
            'Payment getEntityId' => $payment->getEntityId(),
            'Payment getLastTransId' => $payment->getLastTransId(),
            'Payment getMethod' => $payment->getMethod(),
            'Payment getParentId' => $payment->getParentId(),
        ]);

        try {
            $this->logger->debug([
                'Caller class' => $caller,
                'Action'    => 'Before update',
                'Entity id' => $payment->getEntityId(),
                'Parent id' => $payment->getParentId(),
            ]);
            $payment->update(true);
            $this->logger->debug([
                'Caller class' => $caller,
                'Action'    => 'After update',
            ]);
        } catch (Exception $exc) {
            $this->writeln('<error>'.$exc->getMessage().'</error>');
            $this->logger->debug([
                'Action'    => 'Fetching error',
                'msg' => $exc->getMessage(),
            ]);
        }

        $this->logger->debug([
            'Caller class' => $caller,
            'Action'    => 'After fetching',
            'Order state' => $order->getState(),
            'Order status' => $order->getStatus(),
        ]);

        if ($order->getState() === Order::STATE_PAYMENT_REVIEW) {
            if ($order->getStatus() === Order::STATE_CLOSED) {
                $this->logger->debug([
                    'Action'    => 'Blocked updating closed order status',
                ]);
            } else if ($order->getStatus() === Order::STATE_COMPLETE) {
                $this->logger->debug([
                    'Action'    => 'Blocked updating complete order status',
                ]);
                $order = $payment->getOrder();
                $order->setState(Order::STATE_CLOSED);
                $order->setStatus('closed');
                $this->logger->debug([
                    'Action'    => 'Updated to closed',
                ]);
                
            } else {
                $order = $payment->getOrder();
                $order->setState(Order::STATE_NEW);
                $order->setStatus('pending');
                
                $this->logger->debug([
                    'Action'    => 'Updated order with payment review to pending',
                ]);
            }
        }

        // $order->save();

        // $this->logger->debug([
        //     'Caller class' => $caller,
        //     'Action'    => 'First save',
        //     'Order state' => $order->getState(),
        //     'Order status' => $order->getStatus(),
        // ]);

        $this->writeln(
            '<info>'.
            __(
                'Order %1 - Increment Id %2 - state %3',
                $orderId,
                $order->getIncrementId(),
                $order->getState(),
            )
            .'</info>'
        );

        $order->save();

        $this->logger->debug([
            'Caller class' => $caller,
            'Action'    => 'Save',
            'Order state' => $order->getState(),
            'Order status' => $order->getStatus(),
        ]);

        $this->writeln(__('Finished'));

        return $order;
    }
}
