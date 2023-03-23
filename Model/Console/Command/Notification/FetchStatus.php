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
            'Order state' => $order->getState(),
            'Order status' => $order->getStatus(),
        ]);
        
        $payment = $order->getPayment();

        try {
            $this->logger->debug([
                'Caller class' => $caller,
                'Action'    => 'Before update',
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
                // $order = $payment->getOrder();
                // $order->setState(Order::STATE_CLOSED);
                // $order->setStatus('closed');
                
            } else {
                $order = $payment->getOrder();
                $order->setState(Order::STATE_NEW);
                $order->setStatus('pending');
                
                $this->logger->debug([
                    'Action'    => 'Updated order with payment review to pending',
                ]);
            }
        }

        $order->save();

        $this->logger->debug([
            'Caller class' => $caller,
            'Action'    => 'First save',
            'Order state' => $order->getState(),
            'Order status' => $order->getStatus(),
        ]);

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
            'Action'    => 'Second save',
            'Order state' => $order->getState(),
            'Order status' => $order->getStatus(),
        ]);

        $this->writeln(__('Finished'));

        return $order;
    }
}
