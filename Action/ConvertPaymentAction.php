<?php

declare(strict_types=1);

namespace DachcomDigital\Payum\PostFinance\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Model\PaymentInterface;
use Payum\Core\Request\Convert;

class ConvertPaymentAction implements ActionInterface
{
    /**
     * @inheritDoc
     *
     * @param Convert $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getSource();

        $details = ArrayObject::ensureArrayObject($payment->getDetails());
        $details['ORDERID'] = $payment->getNumber();
        $details['CURRENCY'] = $payment->getCurrencyCode();
        $details['AMOUNT'] = $payment->getTotalAmount();
        $details['COM'] = $payment->getDescription();

        $request->setResult((array)$details);
    }

    /**
     * @inheritDoc
     */
    public function supports($request): bool
    {
        return $request instanceof Convert
            && $request->getSource() instanceof PaymentInterface
            && $request->getTo() === 'array';
    }
}
