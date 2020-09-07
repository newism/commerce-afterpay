<?php

namespace newism\commerce\afterpay\responses;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\web\View;
use GuzzleHttp\Psr7\Response;

class PurchaseResponse implements RequestResponseInterface
{
    protected $response;
    protected $sandboxMode;
    protected $data = [];

    public function __construct(Response $response, $sandboxMode = false)
    {
        $this->response = $response;

        $this->sandboxMode = $sandboxMode;

        $this->data = json_decode($response->getBody(), true);
    }

    public function isRedirect(): bool
    {
        return true;
    }

    public function getTransactionReference(): string
    {
        return $this->data['token'];
    }

    public function getRedirectMethod(): string
    {
        return 'POST';
    }

    public function redirect()
    {
        $oldMode = Craft::$app->view->getTemplateMode();
        Craft::$app->view->setTemplateMode(View::TEMPLATE_MODE_CP);
        $html = Craft::$app->view->renderTemplate('newism-commerce-afterpay/redirect.html.twig', ['data' => $this->data, 'sandboxMode' => $this->sandboxMode]);
        Craft::$app->view->setTemplateMode($oldMode);
        ob_start();
        echo $html;
        Craft::$app->end();
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
