<?php

namespace P3\PaymentGateway\Plugin;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Session\SessionStartChecker;

/**
 * Intended to preserve session cookie after submitting POST form from gateway to Magento controller.
 */
class TransparentSessionChecker
{
    private const TRANSPARENT_REDIRECT_PATH = '/paymentgateway/order/process/';

    /**
     * @var Http
     */
    private $request;

    /**
     * @param Http $request
     */
    public function __construct(
        Http $request
    ) {
        $this->request = $request;
    }

    /**
     * Prevents session starting while instantiating P3 transparent redirect controller.
     *
     * @param SessionStartChecker $subject
     * @param bool $result
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCheck(SessionStartChecker $subject, bool $result): bool
    {
        if ($result === false) {
            return false;
        }

        if (strpos((string)$this->request->getPathInfo(), self::TRANSPARENT_REDIRECT_PATH) !== false
            && null !== $this->request->getParam('customerPHPSESSID')
        ) {
            // Hack for fixing Direct use of $_SESSION Superglobal detected but anyway use new_session_id.
            // @note Never used.
            // $session = '_SESSION';
            // $session['new_session_id'] = $this->request->getParam('customerPHPSESSID');
        }

        return true;
    }
}
