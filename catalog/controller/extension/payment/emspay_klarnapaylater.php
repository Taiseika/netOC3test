<?php

class ControllerExtensionPaymentEmspayKlarnaPayLater extends Controller
{
    /**
     * Default currency for Order
     */
    const DEFAULT_CURRENCY = 'EUR';

    /**
     * Payments module name
     */
    const MODULE_NAME = 'emspay_klarnapaylater';

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
        $this->ems = $this->emsHelper->getClientForKlarna($this->config);
    }
    
    /**
     * Method is an event trigger for capturing Klarna shipped status.
     *
     * @param $route
     * @param $data
     */
    public function capture($route, $data)
    {
        $this->load->model('account/order');
        $this->load->model('checkout/order');

        try {
            $emsOrderId = EmsHelper::searchHistoryForOrderKey(
                $this->model_account_order->getOrderHistories(
                    $this->request->get['order_id']
                )
            );

            if ($emsOrderId) {

                $order = $this->model_checkout_order->getOrder(
                    $this->request->get['order_id']
                );

                $capturedStatus = $this->emsHelper->getOrderStatus(
                    EmsHelper::EMS_STATUS_CAPTURED,
                    $this->config
                );

                if ($order['order_status_id'] == $capturedStatus) {
                    $this->ems->captureOrderTransaction($emsOrderId, $this->request->get(['transactions']['id'])

                    );
                };
            }
        } catch (\Exception $e) {
            $this->session->data['error'] = $e->getMessage();
        }
    }

    /**
     * Index Action
     * @return mixed
     */
    public function index()
    {
        $this->language->load('extension/payment/'.static::MODULE_NAME);

        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['text_select_bank'] = $this->language->get('text_select_bank');
        $data['text_error_invalid_dob'] = $this->language->get('error_invalid_dob');
        $data['text_please_enter_dob'] = $this->language->get('text_please_enter_dob');
        $data['text_please_select_gender'] = $this->language->get('text_please_select_gender');
        $data['text_please_select_gender_male'] = $this->language->get('text_please_select_gender_male');
        $data['text_please_select_gender_female'] = $this->language->get('text_please_select_gender_female');
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

                if ($emsOrder['status'] == 'error'){
                    $this->language->load('extension/payment/'.static::MODULE_NAME);
                    $this->session->data['error'] = $emsOrder['transactions'][0]['reason'];
                    $this->session->data['error'] .= $this->language->get('error_another_payment_method');
                    $this->response->redirect($this->url->link('checkout/checkout'));
                } elseif ($emsOrder['status'] == 'cancelled') {
                    $this->response->redirect($this->emsHelper->getFailureUrl($this, $this->session->data['order_id']));
                }

                $this->model_checkout_order->addOrderHistory(
                    $emsOrder['merchant_order_id'],
                    $this->emsHelper->getOrderStatus($emsOrder['status'], $this->config),
                    'EMS Online Klarna Pay Later order: '.$emsOrder['id'],
                    true
                );
                $this->response->redirect($this->emsHelper->getSucceedUrl($this, $this->session->data['order_id']));
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

    /**
     * Generate EMS Online iDEAL order.
     *
     * @param array
     * @return \Ginger\ApiClient
     */
    protected function createOrder(array $orderData)
    {
//        return $this->ems->createOrder([
//            ,          // Currency
//            ,       // Description
//            , // Merchant Order Id
//            null,                            // Expiration Period
//            ,          // Customer information
//            ,    // Extra information
//           ,       // Webhook URL
//                  // Order lines
//        ]);
        return $this->ems->createOrder([
            'amount' => $orderData['amount'],                           // Amount in cents
            'currency' => $orderData['currency'],                       // Currency
            'transactions' => [
                [
                    'payment_method' => 'klarna-pay-later'              // Payment method
                ]
            ],
            'merchant_order_id' => $orderData['merchant_order_id'],     // Merchant Order Id
            'description' => $orderData['description'],                 // Description
            'return_url' => null,                                       // Return URL
            'customer' => $orderData['customer'],                       // Customer Information
            'extra' => ['plugin' => $orderData['plugin_version']],      // Extra information
            'webhook_url' =>  $orderData['webhook_url'],                // Webhook URL
            'order_lines' => $orderData['order_lines']                  // Order lines
        ]);
    }
}
