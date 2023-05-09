<?php
/**
 * Copyright © MercadoPago. All rights reserved.
 *
 * @author      Mercado Pago
 * @license     See LICENSE for license details.
 */

namespace MercadoPago\AdbPayment\Observer;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use MercadoPago\AdbPayment\Model\Console\Command\Adminstrative\PaymentExpiration;
use MercadoPago\AdbPayment\Gateway\Config\Config;

/**
 * Observer Class from Order Cancel.
 */
class OrderCancelAfterObserver implements ObserverInterface
{
    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteria;

    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactions;

    /**
     * @var PaymentExpiration
     */
    protected $paymentExpiration;

    /**
     * @var Config
     */
    protected $config; 
    
    /**
     * @param SearchCriteriaBuilder          $searchCriteria
     * @param TransactionRepositoryInterface $transactions
     * @param PaymentExpiration              $paymentExpiration
     * @param Config $config
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteria,
        TransactionRepositoryInterface $transactions,
        PaymentExpiration $paymentExpiration,
        Config $config
    ) {
        $this->searchCriteria = $searchCriteria;
        $this->transactions = $transactions;
        $this->paymentExpiration = $paymentExpiration;
        $this->config = $config;
    }

    /**
     * Excecute convert finance cost.
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        /* @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getData('order');

        $payment = $order->getPayment();

        $storeId = $order->getStoreId();
        
        $amount = $this->config->formatPrice($order->getBaseGrandTotal(), $storeId);

        $orderId = $order->getId();
        $storeId = $order->getStoreId();

        $searchCriteria = $this->searchCriteria
            ->addFilter('order_id', $orderId)
            ->addFilter('txn_type', 'order')
            ->create();

        /** @var TransactionRepositoryInterface $transaction */
        $transaction = $this->transactions->getList($searchCriteria)->getFirstItem();

        $transactionId = $transaction->getTxnId();

        if ($transactionId) {
            $this->paymentExpiration->expire($transactionId, $storeId);

            $payment->setTransactionId($transactionId.'-expire');
            $payment->setPreparedMessage(__('Order Canceled.'));
            $payment->setIsTransactionPending(false);
            $payment->setIsTransactionDenied(true);
            $payment->setAmountCanceled($amount);
            $payment->setBaseAmountCanceled($amount);
            $payment->setShouldCloseParentTransaction(true);
            $payment->addTransaction(Transaction::TYPE_VOID);
            $payment->save();

            $comment = __('Order Canceled.');
            $order->addStatusHistoryComment($comment, $payment->getOrder()->getStatus());
            $order->save();
        }
    }
}
