<?php

declare(strict_types=1);

namespace DachcomDigital\Payum\PostFinance\Action;

use DachcomDigital\Payum\PostFinance\Api;
use JsonException;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Reply\HttpPostRedirect;
use Payum\Core\Security\GenericTokenFactoryAwareInterface;
use Payum\Core\Security\GenericTokenFactoryAwareTrait;

/**
 * @property Api $api
 */
class CaptureOffSiteAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface, GenericTokenFactoryAwareInterface
{
    use ApiAwareTrait;
    use GatewayAwareTrait;
    use GenericTokenFactoryAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    /**
     * @inheritDoc
     *
     * @param Capture $request
     *
     * @throws JsonException
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        $httpRequest = new GetHttpRequest();
        $this->gateway->execute($httpRequest);

        // We are back from Postfinance site, so we have to just update model.
        if (isset($httpRequest->query['PAYID'])) {
            $model->replace($httpRequest->query);
        } else {
            $extraData = isset($model['COMPLUS']) ? \json_decode($model['COMPLUS'], true, 512, JSON_THROW_ON_ERROR) : [];

            if (!isset($extraData['capture_token']) && $request->getToken()) {
                $extraData['capture_token'] = $request->getToken()->getHash();
            }

            if (!isset($extraData['notify_token']) && $this->tokenFactory && $request->getToken()) {
                $notifyToken = $this->tokenFactory->createNotifyToken(
                    $request->getToken()->getGatewayName(),
                    $request->getToken()->getDetails()
                );

                $extraData['notify_token'] = $notifyToken->getHash();
                $model['PARAMVAR'] = $notifyToken->getHash();

            }

            $model['COMPLUS'] = \json_encode($extraData, JSON_THROW_ON_ERROR);

            // payment/capture/xy
            $targetUrl = $request->getToken()->getTargetUrl();

            // Accept URL
            if (null === $model['ACCEPTURL'] && $request->getToken()) {
                $model['ACCEPTURL'] = $targetUrl;
            }

            // Cancel URL
            if (null === $model['CANCELURL'] && $request->getToken()) {
                $model['CANCELURL'] = $targetUrl;
            }

            throw new HttpPostRedirect(
                $this->api->getOffSiteUrl(),
                $this->api->prepareOffSitePayment($model->toUnsafeArray())
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function supports($request): bool
    {
        return $request instanceof Capture
            && $request->getModel() instanceof \ArrayAccess;
    }
}
