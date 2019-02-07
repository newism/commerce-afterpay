<?php

namespace newism\commerce\afterpay\responses;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\web\View;

class AuthorizationAmountMismatchResponse implements RequestResponseInterface
{
    public function isRedirect(): bool
    {
        return false;
    }

    public function getTransactionReference(): string
    {
        return '';
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
        return '';
    }

    public function getData()
    {
        return null;
    }

    public function getMessage(): string
    {
        return Craft::t('commerce', 'Afterpay authorisation and cart amount no longer match. Please try again.');
    }
}
