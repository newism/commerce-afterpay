<?php

namespace newism\commerce\afterpay\gateways;

use Craft;
use craft\base\Plugin;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\helpers\UrlHelper;
use craft\web\Response as WebResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use newism\commerce\afterpay\events\SendRequestEvent;
use newism\commerce\afterpay\models\forms\PaymentForm;
use newism\commerce\afterpay\responses\AuthorizationAmountMismatchResponse;
use newism\commerce\afterpay\responses\CompletePurchaseResponse;
use newism\commerce\afterpay\responses\GatewayErrorResponse;
use newism\commerce\afterpay\responses\PurchaseResponse;
use newism\commerce\afterpay\responses\RefundResponse;
use yii\base\NotSupportedException;

class AfterpayGateway extends BaseGateway
{
    public const SUPPORTS = [
        'Authorize' => false,
        'Capture' => false,
        'CompleteAuthorize' => false,
        'CompletePurchase' => true,
        'PaymentSources' => false,
        'Purchase' => true,
        'Refund' => true,
        'PartialRefund' => true,
        'Webhooks' => false,
    ];

    public const ENDPOINTS = [
        'AU' => [
            'production' => 'https://api.afterpay.com/v1',
            'sandbox' => 'https://api-sandbox.afterpay.com/v1',
        ],
        'NZ' => [
            'production' => 'https://api.afterpay.com/v1',
            'sandbox' => 'https://api-sandbox.afterpay.com/v1',
        ],
        'US' => [
            'production' => 'https://api.us.afterpay.com/v1',
            'sandbox' => 'https://api-sandbox.us.afterpay.com/v1',
        ],
        'EU' => [
            'production' => 'https://api.eu.afterpay.com/v1',
            'sandbox' => 'https://api.eu-sandbox.afterpay.com/v1',
        ],
    ];

    public const EVENT_BEFORE_SEND_PURCHASE_REQUEST = 'beforeSendPurchaseRequest';
    public const EVENT_BEFORE_SEND_COMPLETE_PURCHASE_REQUEST = 'beforeSendCompletePurchaseRequest';
    public const EVENT_BEFORE_SEND_REFUND_REQUEST = 'beforeSendRefundRequest';

    public $region = 'AU';
    public $merchantId;
    public $merchantKey;
    public $merchantReference;
    public $sandboxMode = false;
    public $userAgentUrl;

    public static function displayName(): string
    {
        return Craft::t('commerce', 'Afterpay');
    }

    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('newism-commerce-afterpay/gateway/settings.twig.html', ['gateway' => $this]);
    }

    public function getPaymentTypeOptions(): array
    {
        return [
            'purchase' => Craft::t('commerce', 'Purchase (Authorize and Capture Immediately)'),
        ];
    }

    public function getPaymentFormHtml(array $params): ?string
    {
        return null;
    }

    public function getPaymentFormModel(): BasePaymentForm
    {
        return new PaymentForm();
    }

    public function supportsPurchase(): bool
    {
        return self::SUPPORTS['Purchase'];
    }

    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        /** @var Order $order */
        $order = $transaction->getOrder();

        $data = [
            'merchant' => [
                'redirectConfirmUrl' => UrlHelper::actionUrl('commerce/payments/complete-payment', [
                    'commerceTransactionId' => $transaction->id,
                    'commerceTransactionHash' => $transaction->hash,
                ]),
                'redirectCancelUrl' => UrlHelper::siteUrl($order->cancelUrl),
            ],
            'merchantReference' => $this->getMerchantReference($order),
            'totalAmount' => [
                'amount' => (float)$order->totalPrice,
                'currency' => $order->paymentCurrency,
            ],
            'consumer' => [
                'phoneNumber' => $order->billingAddress->phone,
                'givenNames' => $order->billingAddress->firstName,
                'surname' => $order->billingAddress->lastName,
                'email' => $order->email,
            ],
            'taxAmount' => [
                'amount' => (float)Currency::round($order->getTotalTax()),
                'currency' => $order->paymentCurrency,
            ],
            'shippingAmount' => [
                'amount' => (float)Currency::round($order->getTotalShippingCost()),
                'currency' => $order->paymentCurrency,
            ],
            'discounts' => array_map(function (OrderAdjustment $adjustment) use ($order) {
                return [
                    'displayName' => $adjustment->name,
                    'amount' => [
                        'amount' => (float)$adjustment->amount,
                        'currency' => $order->paymentCurrency,
                    ],
                ];
            }, $this->getOrderAdjustmentsByType($order, 'discount')),
            'items' => array_map(function (LineItem $lineItem) use ($order) {
                return [
                    'quantity' => (int)$lineItem->qty,
                    'name' => $lineItem->description,
                    'sku' => $lineItem->sku,
                    'price' => [
                        'amount' => (float)$lineItem->salePrice,
                        'currency' => $order->paymentCurrency,
                    ],
                ];
            }, $order->lineItems),
        ];

        if ($order->billingAddress) {
            $data['billing'] = [
                'name' => $order->billingAddress->firstName . ' ' . $order->billingAddress->lastName,
                'line1' => $order->billingAddress->address1,
                'line2' => $order->billingAddress->address2,
                'suburb' => $order->billingAddress->city,
                'state' => $order->billingAddress->stateText,
                'postcode' => $order->billingAddress->zipCode,
                'countryCode' => $order->billingAddress->country->iso,
                'phoneNumber' => $order->billingAddress->phone,
            ];
        }

        if ($order->shippingAddress) {
            $data['shipping'] = [
                'name' => $order->shippingAddress->firstName . ' ' . $order->shippingAddress->lastName,
                'line1' => $order->shippingAddress->address1,
                'line2' => $order->shippingAddress->address2,
                'suburb' => $order->shippingAddress->city,
                'state' => $order->shippingAddress->stateText,
                'postcode' => $order->shippingAddress->zipCode,
                'countryCode' => $order->shippingAddress->country->iso,
                'phoneNumber' => $order->shippingAddress->phone,
            ];
        }

        $endpoint = sprintf(
            '%s/orders',
            self::ENDPOINTS[$this->region][$this->sandboxMode ? 'sandbox' : 'production']
        );

        $event = new SendRequestEvent([
            'transaction' => $transaction,
            'form' => $form,
            'method' => 'POST',
            'endpoint' => $endpoint,
            'payload' => [
                'auth' => [
                    Craft::parseEnv($this->merchantId),
                    Craft::parseEnv($this->merchantKey),
                ],
                'headers' => [
                    'User-Agent' => $this->getUserAgent(),
                ],
                'json' => $data,
            ],
        ]);

        // Raise 'beforeSendPurchaseRequest' event
        $this->trigger(self::EVENT_BEFORE_SEND_PURCHASE_REQUEST, $event);

        // Ping Afterpay
        $client = new Client();
        $tokenResponse = $client->request(
            $event->method,
            $event->endpoint,
            $event->payload
        );

        return new PurchaseResponse(
            $tokenResponse,
            $this->region,
            $this->sandboxMode
        );
    }

    public function supportsCompletePurchase(): bool
    {
        return self::SUPPORTS['CompletePurchase'];
    }

    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        if (!$this->supportsCompletePurchase()) {
            throw new NotSupportedException(Craft::t('commerce', 'Completing purchase is not supported by this gateway'));
        }

        $orderTotal = (float)$transaction->getOrder()->totalPrice;
        $transactionTotal = (float)$transaction->paymentAmount;

        if ($orderTotal !== $transactionTotal) {
            return new AuthorizationAmountMismatchResponse();
        }
        $order = $transaction->getOrder();

        $data = [
            'token' => Craft::$app->getRequest()->getQueryParam('orderToken'),
            'merchantReference' => $this->getMerchantReference($order),
        ];

        $endpoint = sprintf(
            '%s/payments/capture',
            self::ENDPOINTS[$this->region][$this->sandboxMode ? 'sandbox' : 'production']
        );

        $event = new SendRequestEvent([
            'transaction' => $transaction,
            'method' => 'POST',
            'endpoint' => $endpoint,
            'payload' => [
                'auth' => [
                    Craft::parseEnv($this->merchantId),
                    Craft::parseEnv($this->merchantKey),
                ],
                'headers' => [
                    'User-Agent' => $this->getUserAgent(),
                ],
                'json' => $data,
            ],
        ]);

        // Raise 'beforeSendCompletePurchaseRequest' event
        $this->trigger(self::EVENT_BEFORE_SEND_COMPLETE_PURCHASE_REQUEST, $event);

        // Ping Afterpay
        $client = new Client();
        try {
            $tokenResponse = $client->request(
                $event->method,
                $event->endpoint,
                $event->payload
            );
        } catch (BadResponseException $exception) {
            return new GatewayErrorResponse($exception);
        }
        return new CompletePurchaseResponse($tokenResponse);
    }

    public function supportsAuthorize(): bool
    {
        return self::SUPPORTS['Authorize'];
    }

    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        throw new NotSupportedException(Craft::t('commerce', 'Authorization is not supported by this gateway'));
    }

    public function supportsCompleteAuthorize(): bool
    {
        return self::SUPPORTS['CompleteAuthorize'];
    }

    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        throw new NotSupportedException(Craft::t('commerce', 'Complete Authorize is not supported by this gateway'));
    }

    public function supportsCapture(): bool
    {
        return self::SUPPORTS['Capture'];
    }

    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        throw new NotSupportedException(Craft::t('commerce', 'Capture is not supported by this gateway'));
    }

    public function supportsPaymentSources(): bool
    {
        return self::SUPPORTS['PaymentSources'];
    }

    public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
    {
        throw new NotSupportedException(Craft::t('commerce', 'Payment sources are not supported by this gateway'));
    }

    public function deletePaymentSource($token): bool
    {
        throw new NotSupportedException(Craft::t('commerce', 'Payment sources are not supported by this gateway'));
    }

    public function supportsRefund(): bool
    {
        return self::SUPPORTS['Refund'];
    }

    public function supportsPartialRefund(): bool
    {
        return self::SUPPORTS['PartialRefund'];
    }

    public function refund(Transaction $transaction): RequestResponseInterface
    {
        $order = $transaction->getOrder();

        $data = [
            'amount' => [
                'amount' => $transaction->amount,
                'currency' => $transaction->paymentCurrency
            ],
            'merchantReference' => $this->getMerchantReference($order),
            'requestId' => $transaction->hash,
        ];

        $endpoint = sprintf(
            '%s/payments/%s/refund',
            self::ENDPOINTS[$this->region][$this->sandboxMode ? 'sandbox' : 'production'],
            $transaction->getParent()->reference
        );

        $event = new SendRequestEvent([
            'transaction' => $transaction,
            'method' => 'POST',
            'endpoint' => $endpoint,
            'payload' => [
                'auth' => [
                    Craft::parseEnv($this->merchantId),
                    Craft::parseEnv($this->merchantKey),
                ],
                'headers' => [
                    'User-Agent' => $this->getUserAgent(),
                ],
                'json' => $data,
            ],
        ]);

        // Raise 'beforeSendRefundRequest' event
        $this->trigger(self::EVENT_BEFORE_SEND_REFUND_REQUEST, $event);

        // Ping Afterpay
        $client = new Client();
        try {
            $tokenResponse = $client->request(
                $event->method,
                $event->endpoint,
                $event->payload
            );
        } catch (BadResponseException $exception) {
            return new GatewayErrorResponse($exception);
        }

        return new RefundResponse($tokenResponse);
    }

    public function supportsWebhooks(): bool
    {
        return self::SUPPORTS['Webhooks'];
    }

    public function processWebHook(): WebResponse
    {
        throw new NotSupportedException(Craft::t('commerce', 'Webhooks are not supported by this gateway'));
    }

    private function getOrderAdjustmentsByType(Order $order, string $type): array
    {
        return array_values(array_filter(
                $order->getAdjustments(),
                function (OrderAdjustment $orderAdjustment) use ($type) {
                    return $orderAdjustment === $type;
                })
        );
    }

    private function getUserAgent()
    {
        /** @var Plugin $plugin */
        $plugin = Craft::$app->plugins->getPlugin('newism-commerce-afterpay');
        /** @var Plugin $commercePlugin */
        $commercePlugin = Craft::$app->plugins->getPlugin('commerce');

        $url = Craft::parseEnv($this->userAgentUrl);

        return sprintf(
            '%s/%s/%s (Craft/%s; CraftCommerce/%s; PHP/%s; MerchantId/%s) %s',
            $plugin->getHandle(),
            $plugin->getVersion(),
            self::displayName(),
            Craft::$app->getVersion(),
            $commercePlugin->getVersion(),
            PHP_VERSION,
            Craft::parseEnv($this->merchantId),
            $url ? $url : UrlHelper::siteUrl()
        );
    }

    private function getMerchantReference($order)
    {
        $reference = Craft::$app->getView()->renderObjectTemplate($this->merchantReference, $order);

        if (!$reference) {
            return $order->getShortNumber();
        }

        return $reference;
    }
}
