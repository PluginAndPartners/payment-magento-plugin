<?php
/**
 * Copyright © MercadoPago. All rights reserved.
 *
 * @author      Mercado Pago
 * @license     See LICENSE for license details.
 */

namespace MercadoPago\AdbPayment\Gateway\Response;

use InvalidArgumentException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use MercadoPago\AdbPayment\Gateway\Config\Config;

/**
 * Gateway Response Payment Capture.
 */
class CapturePaymentHandler implements HandlerInterface
{

    /**
     * @var Config
     */
    protected $config; 

    /**
     * @param Config $config
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }
    
    /**
     * Handles.
     *
     * @param array $handlingSubject
     * @param array $response
     *
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new InvalidArgumentException('Payment data object should be provided');
        }

        if ($response['RESULT_CODE']) {
            $paymentDO = $handlingSubject['payment'];

            $payment = $paymentDO->getPayment();
            $order = $payment->getOrder();
            $storeId = $order->getStoreId();
            $amount = $this->config->formatPrice($order->getGrandTotal(), $storeId);

            $payment->registerCaptureNotification($amount);
        }
    }
}
