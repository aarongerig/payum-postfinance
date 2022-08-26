<?php

declare(strict_types=1);

namespace DachcomDigital\Payum\PostFinance\Action;

use DachcomDigital\Payum\PostFinance\Api;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Model\PaymentInterface;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetCurrency;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\Notify;

class NotifyAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    /**
     * @inheritDoc
     *
     * @param $request Notify
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $details = ArrayObject::ensureArrayObject($request->getModel());

        $this->gateway->execute($httpRequest = new GetHttpRequest());
        $parameters = \array_change_key_case($httpRequest->query, CASE_UPPER);

        // First notification needs to be ignored:
        // Postfinance comes in way too early!
        if (!isset($details['notification_initiated'])) {
            $details->replace(['notification_initiated' => true]);

            throw new HttpResponse('NOTIFICATION_EARLY_STATE', 500);
        }

        if ($this->api->verifyHash($parameters) === false) {
            throw new HttpResponse('The notification is invalid. Code 1', 400);
        }

        /** @var PaymentInterface $payment */
        $payment = $request->getFirstModel();

        $this->gateway->execute($currency = new GetCurrency($payment->getCurrencyCode()));
        $divisor = 10 ** $currency->exp;

        if ((int) $details['AMOUNT'] !== (int) \round($parameters['AMOUNT'] * $divisor)) {
            throw new HttpResponse('The notification is invalid. Code 2', 400);
        }

        $details->replace($httpRequest->query);

        throw new HttpResponse('OK', 200);
    }

    /**
     * @inheritDoc
     */
    public function supports($request): bool
    {
        return $request instanceof Notify
            && $request->getModel() instanceof \ArrayAccess;
    }
}
