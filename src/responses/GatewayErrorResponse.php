<?php

namespace newism\commerce\afterpay\responses;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use GuzzleHttp\Exception\BadResponseException;

class GatewayErrorResponse implements RequestResponseInterface
{
    protected $exception;
    protected $data;

    public function __construct(BadResponseException $exception)
    {
        $this->exception = $exception;
        $this->data = $exception->hasResponse() ? json_decode($exception->getResponse()->getBody(), true) : '';
    }

    public function isRedirect(): bool
    {
        return false;
    }

    public function getTransactionReference(): string
    {
        return $this->data['errorId'];
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
        return false;
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
        return $this->data['errorCode'];
    }

    public function getData()
    {
        return $this->data;
    }

    public function getMessage(): string
    {
        return $this->data['message'];
    }
}
