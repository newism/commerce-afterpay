<?php

namespace newism\commerce\afterpay\responses;

use craft\commerce\base\RequestResponseInterface;
use GuzzleHttp\Psr7\Response;

class CompletePurchaseResponse implements RequestResponseInterface
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
        return $this->data['id'];
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
        return $this->response->getStatusCode() === 201 && $this->data['status'] === 'APPROVED';
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
