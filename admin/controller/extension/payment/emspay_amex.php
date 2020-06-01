<?php

class ControllerExtensionPaymentEmspayAmex extends Controller
{
    const EMS_MODULE = 'emspay_amex';

    public function index()
    {
        $this->load->controller('extension/payment/emspay_ideal', static::getModuleName());
    }

    static function getModuleName()
    {
        return static::EMS_MODULE;
    }
}
