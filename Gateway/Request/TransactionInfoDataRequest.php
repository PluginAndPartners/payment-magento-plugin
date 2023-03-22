<?php
/**
 * Copyright © MercadoPago. All rights reserved.
 *
 * @license     See LICENSE for license details.
 */

namespace MercadoPago\PaymentMagento\Gateway\Request;

use InvalidArgumentException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;
use MercadoPago\PaymentMagento\Gateway\Config\Config;
use MercadoPago\PaymentMagento\Gateway\Config\ConfigTwoCc;
use MercadoPago\PaymentMagento\Gateway\Data\Order\OrderAdapterFactory;
use MercadoPago\PaymentMagento\Gateway\SubjectReader;

/**
 * Gateway Requests Payment by Card Data.
 */
class TransactionInfoDataRequest implements BuilderInterface
{

    /**
     * Transaction Info block name.
     */
    public const TRANSACTION_INFO = 'transaction_info';

    /**
     * Binary Mode block name.
     */
    public const TRANSACTION_AMOUNT = 'transaction_amount';

    /**
     * Credit card name block name.
     */
    public const PAYMENT_METHOD_ID = 'payment_method_id';

    /**
     * Payment Method Id block name.
     */
    public const INSTALLMENTS = 'installments';

    /**
     * Cc Token block name.
     */
    public const TOKEN = 'token';

    /**
     * Num Cards.
     */
    public const NUM_CARDS = 2;

    /**
     * @var SubjectReader
     */
    protected $subjectReader;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var ConfigTwoCc
     */
    protected $configTwoCc;

    /**
     * @var OrderAdapterFactory
     */
    protected $orderAdapterFactory;

    /**
     * @param SubjectReader       $subjectReader
     * @param Config              $config
     * @param ConfigTwoCc         $configTwoCc
     * @param OrderAdapterFactory $orderAdapterFactory
     */
    public function __construct(
        SubjectReader $subjectReader,
        Config $config,
        ConfigTwoCc $configTwoCc,
        OrderAdapterFactory $orderAdapterFactory
    ) {
        $this->subjectReader = $subjectReader;
        $this->config = $config;
        $this->configTwoCc = $configTwoCc;
        $this->orderAdapterFactory = $orderAdapterFactory;
    }

    /**
     * Build.
     *
     * @param array $buildSubject
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new InvalidArgumentException('Payment data object should be provided');
        }

        $paymentDO = $buildSubject['payment'];
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();
        $result = [];

        $result = $this->getDataPaymetTwoCc($payment);

        return $result;

    }

    /**
     * Data for CC.
     *
     * @param InfoInterface $payment
     *
     * @return array
     */
    public function getDataPaymetTwoCc($payment)
    {
        $instruction = [];

        /** @var OrderAdapterFactory $orderAdapter */
        $orderAdapter = $this->orderAdapterFactory->create(
            ['order' => $payment->getOrder()]
        );

        $grandTotal = $orderAdapter->getGrandTotalAmount();

        $financeCost = $orderAdapter->getFinanceCostAmount();

        $total = $grandTotal - $financeCost;
    
        $instruction[self::TRANSACTION_INFO] = [];

        for ($i = 0; $i < self::NUM_CARDS; $i++):
            $cardInfo = [
                self::TRANSACTION_AMOUNT => (double) $payment->getAdditionalInformation('card_'.$i.'_amount'),
                self::INSTALLMENTS       => (int) $payment->getAdditionalInformation('card_'.$i.'_installments') ?: 1,
                self::TOKEN              => $payment->getAdditionalInformation('card_'.$i.'_number_token'),
                self::PAYMENT_METHOD_ID  => strtolower((string) $payment->getAdditionalInformation('card_'.$i.'_type')),
            ];

            array_push($instruction[self::TRANSACTION_INFO], $cardInfo);
        endfor;

        return $instruction;
    }

}
