<?php

declare(strict_types=1);

namespace DachcomDigital\Payum\PostFinance\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Request\GetStatusInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use PostFinance\Ecommerce\EcommercePaymentResponse;
use PostFinance\PaymentResponse;

class StatusAction implements ActionInterface
{
    /**
     * @inheritDoc
     *
     * @param GetStatusInterface $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = new ArrayObject($request->getModel());

        if (null === $model['STATUS']) {
            $request->markNew();

            return;
        }

        $status = (int) $model['STATUS'];

        switch ($status) {
            case PaymentResponse::STATUS_AUTHORISED:
                $request->markAuthorized();
                break;
            case PaymentResponse::STATUS_PAYMENT_REQUESTED:
            case PaymentResponse::STATUS_PAYMENT:
            case PaymentResponse::STATUS_AUTHORISATION_CANCELLATION_WAITING:
                $request->markCaptured();
                break;
            case PaymentResponse::STATUS_INCOMPLETE_OR_INVALID:
            case PaymentResponse::STATUS_AUTHORISATION_REFUSED:
            case PaymentResponse::STATUS_PAYMENT_REFUSED:
                $request->markFailed();
                break;
            case PaymentResponse::STATUS_REFUND:
                $request->markRefunded();
                break;
            case PaymentResponse::STATUS_CANCELLED_BY_CLIENT:
            case PaymentResponse::STATUS_PAYMENT_DELETED:
                $request->markCanceled();
                break;
            default:
                $request->markUnknown();
                break;
        }
    }

    /**
     * @inheritDoc
     */
    public function supports($request): bool
    {
        return $request instanceof GetStatusInterface
            && $request->getModel() instanceof \ArrayAccess;
    }
}
