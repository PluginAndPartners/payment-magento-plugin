<?php
/**
 * Copyright © MercadoPago. All rights reserved.
 *
 * @author      Bruno Elisei <brunoelisei@o2ti.com>
 * @license     See LICENSE for license details.
 */

namespace MercadoPago\PaymentMagento\Block\Order\Success;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order\Config;
use Magento\Framework\View\Asset\Repository;

/**
 * Success page additional information.
 */
class Additional extends Template
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var Config
     */
    protected $orderConfig;

    /**
     * @var HttpContext
     */
    protected $httpContext;

    /**
     * @var Repository
     */
    protected $_assetRepo;

    /**
     * @param Context     $context
     * @param Session     $checkoutSession
     * @param Config      $orderConfig
     * @param HttpContext $httpContext
     * @param array       $data
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        Config $orderConfig,
        HttpContext $httpContext,
        Repository $assetRepo,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->orderConfig = $orderConfig;
        $this->httpContext = $httpContext;
        $this->_assetRepo = $assetRepo;

        $methodCode = $this->getMethodCode();

        if ($methodCode === 'mercadopago_paymentmagento_payment_methods_off') { 
            $this->setTemplate('MercadoPago_PaymentMagento::order/success/payment-method-off.phtml');
        } elseif ($methodCode === 'mercadopago_paymentmagento_pix') {
            $this->setTemplate('MercadoPago_PaymentMagento::order/success/pix.phtml');
        } elseif (str_contains($methodCode, 'mercadopago_paymentmagento')) {
            $this->setTemplate('MercadoPago_PaymentMagento::order/success/default.phtml');
        }
    }

    /**
     * Get Payment.
     *
     * @return \Magento\Payment\Model\MethodInterface
     */
    public function getPayment()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        return $order->getPayment()->getMethodInstance();
    }

    /**
     * Method Code.
     *
     * @return string
     */
    public function getMethodCode()
    {
        return $this->getPayment()->getCode();
    }

    /**
     * Info payment.
     *
     * @param string $info
     *
     * @return string
     */
    public function getInfo(string $info)
    {
        return  $this->getPayment()->getInfoInstance()->getAdditionalInformation($info);
    }

    /**
     * Get Logo Mercado Pago.
     *     *
     * @return string
     */
    public function getLogoMP()
    {
       return $this->_assetRepo->getUrl('MercadoPago_PaymentMagento::images/core/logo.svg');
    }
}
