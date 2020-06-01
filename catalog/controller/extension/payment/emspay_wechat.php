<?php

/**
 * Class ControllerPaymentEmspayWechat
 */
class ControllerExtensionPaymentEmspayWechat extends Controller
{
    /**
     * Default currency for EMS Online Order
     */
    const DEFAULT_CURRENCY = 'EUR';

    /**
     * Payments module name
     */
    const MODULE_NAME = 'emspay_wechat';

    /**
     * @var \Ginger\Ginger
     */
    public $ems;

    /**
     * @var EmsHelper
     */
    public $emsHelper;

    /**
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);

        $this->emsHelper = new EmsHelper(static::MODULE_NAME);
        $this->ems = $this->emsHelper->getClient($this->config);
    }

    /**
     * Index Action
     * @return mixed
     */
    public function index()
    {
        $this->language->load('extension/payment/'.static::MODULE_NAME);

        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['action'] = $this->url->link('extension/payment/'.static::MODULE_NAME.'/confirm');

        return $this->load->view('extension/payment/'.static::MODULE_NAME, $data);
    }

    /**
     * Order Confirm Action
     */
    public function confirm()
    {
        try {
            $this->load->model('checkout/order');
            $orderInfo = $this->model_checkout_order->getOrder($this->session->data['order_id']);

            if ($orderInfo) {
                $emsOrderData = $this->emsHelper->getOrderData($orderInfo, $this);
                $emsOrder = $this->createOrder($emsOrderData);
                var_dump($emsOrder);
                exit();

                if ($emsOrder['status'] == 'error') {
                    $this->language->load('extension/payment/'.static::MODULE_NAME);
                    $this->session->data['error'] = $emsOrder['transactions'][0]['reason'];
                    $this->session->data['error'] .= $this->language->get('error_another_payment_method');
                    $this->response->redirect($this->url->link('checkout/checkout'));
                }

                $this->response->redirect($emsOrder['transactions'][0]['payment_url']);
            }
        } catch (\Exception $e) {
            $this->session->data['error'] = $e->getMessage();
            $this->response->redirect($this->url->link('checkout/checkout'));
        }
    }

    /**
     * Callback Action
     */
    public function callback()
    {
        $this->emsHelper->loadCallbackFunction($this);
    }

    /**
     * Pending order processing page
     *
     * @return mixed
     */
    public function processing()
    {
        return $this->emsHelper->loadProcessingPage($this);
    }

    /**
     * Pending order processing page
     *
     * @return mixed
     */
    public function pending()
    {
        $this->cart->clear();

        return $this->emsHelper->loadPendingPage($this);
    }

    /**
     * Generate order.
     *
     * @param array
     * @return Ginger\ApiClient\
     */
    protected function createOrder(array $orderData)
    {
        return [
            "amount" => 100,
            "client" =>
                [
                    "user_agent" => "Ginger-PHP/2.0.0 (Darwin; PHP 7.4.2)"
                ],
            "created" => "2020-05-06T07:32:58.429405+00:00",
            "currency" => "EUR",
            "customer" =>
                [
                    "address" => "Orteliusstraat 63 1057 BJ Amsterdam",
                    "address_type" => "billing",
                    "country" => "NL",
                    "email_address" => "klyameraa@gmail.com",
                    "first_name" => "Doe",
                    "last_name" => "John",
                    "locale" => "en_GB",
                    "merchant_customer_id" => "407",
                    "phone_numbers" => "06-91650120"
                ],
            "description" => "Your order 55 at virtuemart",
            "extra" =>
                [
                    "plugin" =>
                        [
                            "plugin" => "Joomla Virtuemart v1.1.0"
                        ]
                ],
            "flags" => "is-test",
            "id" => "af272cc6-fd64-42be-b71a-9114b6da2a09",
            "last_transaction_added" => "2020-05-06T07:32:58.833748+00:00",
            "merchant_id" => "298939b0-cb8b-4e1d-b388-3efae9205fff",
            "merchant_order_id" => "55",
            "modified" => "2020-05-06T07:32:59.472998+00:00",
            "project_id" => "9b595f0e-19a5-4b42-9a18-ac6ea949767f",
            "return_url" => "http://localhost:8888/virtuemart/?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=3",
            "status" => "new",
            "transactions" =>
                [
                    [
                        "amount" => 100,
                        "balance" => "test",
                        "created" => "2020-05-06T07:32:58.833748+00:00",
                        "credit_debit" => "credit",
                        "currency" => "EUR",
                        "description" => "Your order 55 at virtuemart",
                        "events" =>
                            [
                                "event" => "new",
                                "id" => "5def2cd3-60b3-4047-a1eb-f6fe69961e85",
                                "noticed" => "2020-05-06T07:32:59.045105+00:00",
                                "occurred" => "2020-05-06T07:32:58.833748+00:00",
                                "source" => "set_status",
                            ],
                        "expiration_period" => "PT30M",
                        "id" => "b1bff91c-b164-4d96-a10b-d47d415f5e12",
                        "is_capturable" => false,
                        "merchant_id" => "298939b0-cb8b-4e1d-b388-3efae9205fff",
                        "modified" => "2020-05-06T07:32:59.552799+00:00",
                        "order_id" => "af272cc6-fd64-42be-b71a-9114b6da2a09",
                        "payment_method" => "ideal",
                        "payment_method_brand" => "ideal",
                        "payment_method_details" =>
                            [
                                "issuer_id" => "INGBNL2A"
                            ],
                        "payment_url" => "https://api.online.emspay.eu/redirect/b1bff91c-b164-4d96-a10b-d47d415f5e12/to/payment/",
                        "product_type" => "ginger",
                        "project_id" => "9b595f0e-19a5-4b42-9a18-ac6ea949767f",
                        "status" => "new"
                    ]
                ],
            "webhook_url" =>"http://localhost:8888/virtuemart/?option=com_virtuemart&view=pluginresponse&task=pluginnotification&pm=3"
        ];
        /*return $this->ems->createOrder([
            'amount' => $orderData['amount'],                         // Amount in cents
            'currency' => $orderData['currency'],                     // Currensi
            'description' => $orderData['description'],               // Description
            'merchant_order_id' => $orderData['merchant_order_id'],   // Merchant Order Id
            'return_url' => $orderData['return_url'],                 // Return URL
            null,                                                     // Expiration Period
            'customer' => $orderData['customer'],                     // Customer information
            'extra' =>                                                // Extra information
            [
                'plugin' =>
                    [
                        'plugin' => $orderData['plugin']
                    ]
            ],
            'webhook_url' => $orderData['webhook_url']                // Webhook URL
        ]);*/
    }

    /**
     * Webhook action is called by API when transaction status is updated
     *
     * @return void
     */
    public function webhook()
    {
        $this->load->model('checkout/order');
        $webhookData = json_decode(file_get_contents('php://input'), true);
        $this->emsHelper->processWebhook($this, $webhookData);
    }
}
