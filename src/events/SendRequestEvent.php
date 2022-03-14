<?php
namespace newism\commerce\afterpay\events;

use yii\base\Event;

class SendRequestEvent extends Event
{
    // Properties
    // ==========================================================================

    public $transaction;
    public $form;
    public $method;
    public $endpoint;
    public $payload;
}
