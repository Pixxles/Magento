<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   P3
 * @package    PaymentGateway
 * @copyright  Copyright (c) 2017 P3
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace P3\PaymentGateway\Controller\Order;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\Redirect;
use P3\PaymentGateway\Model\Method\P3Method;
use P3\PaymentGateway\Model\Source\Integration;
use Psr\Log\LoggerInterface;

use Magento\Quote\Model\QuoteFactory;

class Process extends Action implements HttpPostActionInterface, HttpGetActionInterface, CsrfAwareActionInterface
{
    /**
     * @var P3Method
     */
    private $gateway;

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Session
     */
    private $checkoutSession;

    public function __construct(
        Context         $context,
        P3Method        $model,
        LoggerInterface $logger,
        Session         $checkoutSession
    )
    {
        parent::__construct($context);

        $this->gateway = $model;
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        try {
            // Make sure we have something to submit for payment
            if ($this->getRequest()->getMethod() === 'GET'
                && $this->checkoutSession->hasQuote() === false
                && $this->checkoutSession->getLastRealOrder()
                && in_array($this->gateway->integrationType, [
                    Integration::TYPE_HOSTED,
                    Integration::TYPE_HOSTED_MODAL,
                    Integration::TYPE_HOSTED_EMBEDDED
                ])
            ) {
                $result = $this->gateway->processHostedRequest();
                $this->getResponse()->setBody($result);
                return $this->getResponse();
            }

            if ($this->gateway->integrationType === Integration::TYPE_DIRECT) {
                $response = $this->gateway->processDirectRequest();
            }

            $data = $response ?? $this->getRequest()->getPost()->toArray();

            $process = $this->gateway->processResponse($data);
            if (!empty($process)) {
                $this->getResponse()->setBody($process);
                return $this->getResponse();
            }

            $responseMessage = $data['responseMessage'];

            // If the payment was successfull redirect to success page.
            if ($data['responseCode'] == 0) {
                $this->messageManager->addSuccessMessage(__('Payment complete'));
                return $this->redirect('checkout/onepage/success');
            } else {
                // If the payment was not sucessfull then either redirect back
                // to the cart if module setting 'redirect to checkout on pay'
                // is true and restore the cart/session or redirect to failure page.
                if ($this->gateway->redirectToCheckoutOnPayFail) {

                    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                    $order = $objectManager->create('\Magento\Sales\Model\Order')->loadByIncrementId($this->getRequest()->getCookie('lastOrderID'));
                    $quoteFactory = $objectManager->create('\Magento\Quote\Model\QuoteFactory');
                    $quote = $quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());

                    if ($quote->getId()) {
                        $quote->setIsActive(1)->setReservedOrderId(null)->save();
                        $this->checkoutSession->replaceQuote($quote);
                        $this->messageManager->addErrorMessage("Payment Failed - $responseMessage.");
                        return $this->redirect('checkout/cart', $data);
                    }

                } else {
                    // Redirect to failure page with error.
                    $this->messageManager->addErrorMessage("Payment Failed - $responseMessage.");

                    if (isset($data)) {
                        $this->gateway->onFailedTransaction($data);
                    }
                    return $this->redirect('checkout/onepage/failure', $data);
                }
            }

        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), $exception->getTrace());
            $this->messageManager->addErrorMessage(__('Something went wrong with the payment, we were not able to process it, please contact support.'));

            if (isset($data)) {
                $this->gateway->onFailedTransaction($data);
            }

            return $this->redirect('checkout/cart');
        }

        // If the response can't be handled redirect to cart page with error.
        $this->messageManager->addErrorMessage(__('Something went wrong with the payment, we were not able to process it, please contact support.'));
        return $this->redirect('checkout/cart');
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    protected function redirect($path, $data = null)
    {
        if ($this->getRequest()->isXmlHttpRequest()) {
            if ((isset($data['responseCode']) && $data['responseCode'] != 0)) {

                $result = $this->resultFactory->create('raw');
                $contents = <<<SCRIPT
<script>window.top.location.href = "{$this->_url->getUrl($path)}";</script>
SCRIPT;

                $result->setContents($contents);

            } else {
                /** @var Json $result */
                $result = $this->resultFactory->create('json');
                $result->setData(['success' => 'true', 'path' => $path]);
            }

        } elseif ($this->gateway->integrationType === Integration::TYPE_HOSTED_EMBEDDED) {
            /** @var Raw $result */
            $result = $this->resultFactory->create('raw');
            $contents = <<<SCRIPT
<script>window.top.location.href = "{$this->_url->getUrl($path)}";</script>
SCRIPT;
            $result->setContents($contents);
        } else {
            /** @var Redirect $result */
            $result = $this->resultFactory->create('redirect');
            $result->setPath($path);
        }

        return $result;
    }
}
