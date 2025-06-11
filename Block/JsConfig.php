<?php

namespace P3\PaymentGateway\Block;

use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;

class JsConfig extends Template
{
    const XML_PATH_ENABLED = 'payment/P3_PaymentGateway/active';
    const XML_PATH_INTEGRATION_TYPE = 'payment/P3_PaymentGateway/integration_type';


    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    private $scopeConfig;

    public function __construct(
        Template\Context                                   $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        array                                              $data = []
    )
    {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function isDirectPaymentType(): bool
    {
        return $this->scopeConfig->getValue(
                self::XML_PATH_INTEGRATION_TYPE,
                ScopeInterface::SCOPE_STORE
            ) === 'direct';
    }

    public function getJsConfig(): array
    {
        $sessionId = substr(uniqid() . uniqid() . uniqid(), 0, 32);

        return [
            'integrationType' => $this->scopeConfig->getValue(self::XML_PATH_INTEGRATION_TYPE, ScopeInterface::SCOPE_STORE),
            'sessionId' => $sessionId,
            'environment' => 'PROD',
            'merchant_id' => 102444,
        ];
    }
}

