<?php

namespace newism\commerce\afterpay\responses;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\web\View;
use GuzzleHttp\Psr7\Response;

class RefundResponse implements RequestResponseInterface
{
    protected $response;
    protected $data = [];

    public function __construct(Response $response)
    {
        $this->response = $response;
        $this->data = json_decode($response->getBody(), true);
    }

    public function isRedirect(): bool
    {
        return false;
    }

    public function getTransactionReference(): string
    {
        return $this->data['refundId'];
    }

    public function getRedirectMethod(): string
    {
        return '';
    }

    public function redirect()
    {
    }

    public function isSuccessful(): bool
    {
        return $this->response->getStatusCode() === 201;
    }

    public function isProcessing(): bool
    {
        return false;
    }

    public function getRedirectData(): array
    {
        return [];
    }

    public function getRedirectUrl(): string
    {
        return '';
    }

    public function getCode(): string
    {
        return $this->response->getStatusCode();
    }

    public function getData()
    {
        return $this->data;
    }

    public function getMessage(): string
    {
        return '';
    }
}
