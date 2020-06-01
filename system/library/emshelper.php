<?php

use Ginger\Ginger;

/**
 * Class EmsHelper
 */
class EmsHelper
{
    /**
     * EndPoint clear pararm
     */
    const ENDPOINT_CLEAR = '';
    /**
     * EMS Online OpenCart plugin version
     */
    const PLUGIN_VERSION = '2.0.0';

    /**
     * Default currency for Order
     */
    const DEFAULT_CURRENCY = 'EUR';

    /**
     * @var string
     */
    protected $paymentMethod;

    /**
     * EMS Online Order statuses
     */
    const EMS_STATUS_EXPIRED = 'expired';
    const EMS_STATUS_NEW = 'new';
    const EMS_STATUS_PROCESSING = 'processing';
    const EMS_STATUS_COMPLETED = 'completed';
    const EMS_STATUS_CANCELLED = 'cancelled';
    const EMS_STATUS_ERROR = 'error';
    const EMS_STATUS_CAPTURED = 'captured';

    /**
     * @param string $paymentMethod
     */
    public function __construct($paymentMethod)
    {
        require_once(DIR_SYSTEM.'library/emspay/ginger-php/vendor/autoload.php');

        $this->paymentMethod = $paymentMethod;
    }

    /**
     * @param object $config
     * @return \Ginger\ApiClient
     */
    public function getClient($config)
    {
        return $this->getGignerClinet(
                $config->get($this->getPaymentSettingsFieldName('api_key')),
                $config->get($this->getPaymentSettingsFieldName('bundle_cacert'))
               );
    }
    
    /**
     * @param object $config
     * @return \Ginger\ApiClient
     */
    public function getClientForAfterPay($config)
    {
        return $this->getGignerClinet(
                $config->get($this->getPaymentSettingsFieldName('afterpay_test_api_key'))
                ?: $config->get($this->getPaymentSettingsFieldName('api_key')),
                $config->get($this->getPaymentSettingsFieldName('bundle_cacert'))
               );
    }
    
    /**
     * @param object $config
     * @return \Ginger\ApiClient
     */
    public function getClientForKlarna($config)
    {
        return $this->getGignerClinet(
                $config->get($this->getPaymentSettingsFieldName('klarna_test_api_key'))
                ?: $config->get($this->getPaymentSettingsFieldName('api_key')),
                $config->get($this->getPaymentSettingsFieldName('bundle_cacert'))
               );
    }

    /**
     * @param object $config
     * @return string $bunldeSert
     */
    protected function getCaCert()
    {
        return __DIR__.'emspay/ginger-php/assets/cacert.pem';
    }

    /**
     * create a gigner clinet instance
     *
     * @param string $apiKey
     * @param boolean $bunldeSert
     * @return \Ginger\ApiClient
     */
    protected function getGignerClinet($apiKey, $bunldeSert = false)
    {
        $ems = Ginger::createClient(self::ENDPOINT_CLEAR, $apiKey, $bunldeSert ? [CURLOPT_CAINFO => EmsHelper::getCaCert()] : []);
        
        return $ems;
    }

    /**
     * Method maps EMS Online order status to OpenCart specific
     *
     * @param string $emsOrderStatus
     * @return string
     */
    public function getOrderStatus($emsOrderStatus, $config)
    {
        switch ($emsOrderStatus) {
            case EmsHelper::EMS_STATUS_NEW:
                $orderStatus = $config->get($this->getPaymentSettingsFieldName('order_status_id_new'));
                break;
            case EmsHelper::EMS_STATUS_EXPIRED:
                $orderStatus = $config->get($this->getPaymentSettingsFieldName('order_status_id_expired'));
                break;
            case EmsHelper::EMS_STATUS_PROCESSING:
                $orderStatus = $config->get($this->getPaymentSettingsFieldName('order_status_id_processing'));
                break;
            case EmsHelper::EMS_STATUS_COMPLETED:
                $orderStatus = $config->get($this->getPaymentSettingsFieldName('order_status_id_completed'));
                break;
            case EmsHelper::EMS_STATUS_CANCELLED:
                $orderStatus = $config->get($this->getPaymentSettingsFieldName('order_status_id_cancelled'));
                break;
            case EmsHelper::EMS_STATUS_ERROR:
                $orderStatus = $config->get($this->getPaymentSettingsFieldName('order_status_id_error'));
                break;
            case EmsHelper::EMS_STATUS_CAPTURED:
                $orderStatus = $config->get($this->getPaymentSettingsFieldName('order_status_id_captured'));
                break;
            default:
                $orderStatus = $config->get($this->getPaymentSettingsFieldName('order_status_id_new'));
                break;
        }

        return $orderStatus;
    }

    /**
     * @param array $orderInfo
     * @return array
     */
    public function getCustomerInformation(array $orderInfo, $paymentMethod)
    {
        $gender = array_key_exists('gender', $paymentMethod->request->post)
            ? $paymentMethod->request->post['gender'] : null;

        $dob = array_key_exists('dob', $paymentMethod->request->post)
            ? date("Y-m-d", strtotime($paymentMethod->request->post['dob'])) : null;

        $customer = array_filter([
            'address_type' => 'customer',
            'country' => $orderInfo['payment_iso_code_2'],
            'email_address' => $orderInfo['email'],
            'first_name' => $orderInfo['payment_firstname'],
            'last_name' => $orderInfo['payment_lastname'],
            'merchant_customer_id' => $orderInfo['customer_id'],
            'phone_numbers' => [$orderInfo['telephone']],
            'address' => implode("\n", array_filter(array(
                $orderInfo['shipping_company'],
                $orderInfo['shipping_address_1'],
                $orderInfo['shipping_address_2'],
                $orderInfo['shipping_postcode']." ".$orderInfo['shipping_city']
            ))),
            'locale' => self::formatLocale($orderInfo['language_code']),
            'ip_address' => filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP),
            'gender' => $gender,
            'birthdate' => $dob,
            'additional_addresses' => [
                [
                    'address_type' => 'billing',
                    'address' => implode("\n", array_filter(array(
                $orderInfo['payment_company'],
                $orderInfo['payment_address_1'],
                $orderInfo['payment_address_2'],
                $orderInfo['payment_postcode']." ".$orderInfo['payment_city']
            ))),
                    'country' => $orderInfo['payment_iso_code_2'],
                ],
            ]
        ]);

        return $customer;
    }

    /**
     * @param array $orderInfo
     * @param object $language
     * @return string
     */
    public function getOrderDescription($orderId, $paymentMethod)
    {
        $paymentMethod->language->load('extension/payment/emspay_common');
  
        return sprintf($paymentMethod->language->get('text_your_order_at'), $orderId, $paymentMethod->config->get('config_name'));
    }

    /**
     * @param array $orderInfo
     * @param object $currency
     * @return int
     */
    public function getAmountInCents($orderInfo, $currency)
    {
        $amount = $currency->format(
            $orderInfo['total'],
            $orderInfo['currency_code'],
            $orderInfo['currency_value'],
            false
        );

        return (int) round($amount * 100);
    }

    /**
     * @param $amount
     * @return int|null
     */
    public static function formatAmountToCents($amount)
    {
        return (int) round($amount * 100);
    }

    /**
     * @param string $fieldName
     * @return string
     */
    public function getPaymentSettingsFieldName($fieldName)
    {
        return 'payment_' . $this->paymentMethod.'_'.$fieldName;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return static::DEFAULT_CURRENCY;
    }

    /**
     * @param string $locale
     * @return mixed
     */
    public function formatLocale($locale)
    {
        return strstr($locale, '-', true);
    }

    /**
     * @param array $orderInfo
     * @param object $paymentMethod
     * @return array
     */
    public function getOrderData(array $orderInfo, $paymentMethod)
    {
        $webhookUrl = $paymentMethod->config->get($this->getPaymentSettingsFieldName('send_webhook'))
            ? $paymentMethod->url->link('extension/payment/'.$this->paymentMethod.'/webhook') : null;

        $issuerId = array_key_exists('issuer_id', $paymentMethod->request->post)
            ? $paymentMethod->request->post['issuer_id'] : null;

        $total_amount_in_cents = $this->getAmountInCents($orderInfo, $paymentMethod->currency);

        return [
            'amount' => $total_amount_in_cents,
            'currency' => $this->getCurrency(),
            'merchant_order_id' => $orderInfo['order_id'],
            'return_url' => $paymentMethod->url->link('extension/payment/'.$this->paymentMethod.'/callback'),
            'description' => $this->getOrderDescription($orderInfo['order_id'], $paymentMethod),
            'customer' => $this->getCustomerInformation($orderInfo, $paymentMethod),
            'issuer_id' => $issuerId,
            'webhook_url' => $webhookUrl,
            'payment_info' => [],
            'order_lines' => $this->getOrderLines($orderInfo, $paymentMethod, $total_amount_in_cents),
            'plugin_version' => ['plugin' => static::getPluginVersion()]
        ];
    }

    /**
     * Method processes calls to webhook url
     *
     * @param object $paymentMethod
     * @param array $webhookData
     * @return void
     */
    public function processWebhook($paymentMethod, array $webhookData)
    {
        if ($webhookData['event'] == 'status_changed') {
            $emsOrder = $paymentMethod->ems->getOrder($webhookData['order_id']);
            $orderInfo = $paymentMethod->model_checkout_order->getOrder(['merchant_id']);
            if ($orderInfo) {
                $paymentMethod->model_checkout_order->addOrderHistory(
                    $emsOrder->ems->get($orderInfo['merchant_id']),
                    $paymentMethod->emsHelper->getOrderStatus($emsOrder->ems->get($orderInfo['status']), $paymentMethod->config),
                    'Status changed for order: '.$emsOrder->ems->get($emsOrder['id']),
                    true
                );
            }
        }
    }

    /**
     * Method prepares Ajax response for processing page
     *
     * @param object $paymentMethod
     */
    public function checkStatusAjax($paymentMethod)
    {
        $orderId = $paymentMethod->request->get['order_id'];
        $emsOrder = $paymentMethod->ems->getOrder($orderId);

        if ($emsOrder->ems->get($emsOrder['status'])
        ) {
            $response = [
                'redirect' => false
            ];
        } else {
            $response = [
                'redirect' => true
            ];
        }

        die(json_encode($response));
    }

    /**
     * @param object $paymentMethod
     * @return mixed
     */
    public function loadProcessingPage($paymentMethod)
    {
        if (isset($paymentMethod->request->post['processing'])) {
            $this->checkStatusAjax($paymentMethod);
        }

        return $paymentMethod->response->setOutput(
            $paymentMethod->load->view(
                'extension/payment/emspay_processing',
                $this->getPageData($paymentMethod)
            )
        );
    }

    /**
     * @param object $paymentMethod
     * @return mixed
     */
    public function loadPendingPage($paymentMethod)
    {
        return $paymentMethod->response->setOutput(
            $paymentMethod->load->view(
                'extension/payment/emspay_pending',
                $this->getPageData($paymentMethod)
            )
        );
    }

    /**
     * @param $paymentMethod
     * @return array
     */
    public function getPageData($paymentMethod)
    {
        $paymentMethod->load->language('extension/payment/'.$this->paymentMethod);
        $paymentMethod->load->language('checkout/success');
        $paymentMethod->load->language('extension/payment/emspay_common');

        return [
            'breadcrumbs' => $this->getBreadcrumbs($paymentMethod),
            'fallback_url' => $this->getPendingUrl($paymentMethod),
            'callback_url' => $this->getCallbackUrl($paymentMethod),

            'header' => $paymentMethod->load->controller('common/header'),
            'footer' => $paymentMethod->load->controller('common/footer'),
            'column_left' => $paymentMethod->load->controller('common/column_left'),
            'column_right' => $paymentMethod->load->controller('common/column_right'),
            'content_top' => $paymentMethod->load->controller('common/content_top'),
            'content_bottom' => $paymentMethod->load->controller('common/content_bottom'),

            'order_description_text' => $this->getOrderDescription($this->getOrderIdFromPaymentMethod($paymentMethod), $paymentMethod),
            'text_processing' => $paymentMethod->language->get('text_processing'),
            'processing_message' => $paymentMethod->language->get('processing_message'),
            'pending_text' => $paymentMethod->language->get('pending_text'),
            'pending_message' => $paymentMethod->language->get('pending_message'),
            'pending_message_sub' => $paymentMethod->language->get('pending_message_sub'),
            'button_continue' => $paymentMethod->language->get('button_continue'),

            'continue' => $paymentMethod->url->link('common/home'),
        ];
    }
    
    /**
     * @param $paymentMethod
     * @return string
     */
    protected function getOrderIdFromPaymentMethod($paymentMethod)
    {
        $emsOrder = $paymentMethod->ems->getOrder($paymentMethod->request->get['order_id']);
        return (!empty($emsOrder) && $emsOrder->ems->get($emsOrder['merchant_id']) !== null) ? $emsOrder->ems->get($emsOrder['merchant_id']) : '';
    }
    
    /**
     * @param $paymentMethod
     */
    public function loadCallbackFunction($paymentMethod)
    {
        $paymentMethod->load->model('checkout/order');
        $emsOrder = $paymentMethod->ems->getOrder($paymentMethod->request->get['order_id']);
        $orderInfo = $paymentMethod->model_checkout_order->getOrder($emsOrder['merchant_id']);
        if ($orderInfo) {
            $paymentMethod->model_checkout_order->addOrderHistory(
                $emsOrder->ems->get($emsOrder['merchant_id']),
                $paymentMethod->emsHelper->getOrderStatus($emsOrder->ems->get($emsOrder['status']), $paymentMethod->config),
                'EMS Online order: '.$emsOrder->ems->get($emsOrder['id']),
                true
            );
            if ($emsOrder->ems->get($emsOrder['status'])->isCompleted()) {
                $paymentMethod->response->redirect($this->getSucceedUrl($paymentMethod, $orderInfo['order_id']));
            } elseif ($emsOrder->ems->get($emsOrder['status'])->isProcessing() || $emsOrder->ems->get($emsOrder['status'])->isNew()) {
                $paymentMethod->response->redirect($paymentMethod->emsHelper->getProcessingUrl($paymentMethod));
            } else {
                $paymentMethod->response->redirect($this->getFailureUrl($paymentMethod, $orderInfo['order_id']));
            }
        }
    }
    
    /**
     * @param $paymentMethod
     * @param int $orderId
     * @return string
     */
    public function getSucceedUrl($paymentMethod, $orderId)
    {
        return htmlspecialchars_decode(
            $paymentMethod->url->link(
                'extension/payment/emspay_success',
                ['order_id' => $orderId]
            )
        );
    }
    
    /**
     * @param $paymentMethod
     * @param int $orderId
     * @return string
     */
    public function getFailureUrl($paymentMethod, $orderId)
    {
        return htmlspecialchars_decode(
            $paymentMethod->url->link(
                'extension/payment/emspay_failure',
                ['order_id' => $orderId]
            )
        );
    }

    /**
     * @param $paymentMethod
     * @return array
     */
    public function getBreadcrumbs($paymentMethod)
    {
        return [
            [
                'text' => $paymentMethod->language->get('text_home'),
                'href' => $paymentMethod->url->link('common/home')
            ],
            [
                'text' => $paymentMethod->language->get('text_basket'),
                'href' => $paymentMethod->url->link('checkout/cart')
            ],
            [
                'text' => $paymentMethod->language->get('text_checkout'),
                'href' => $paymentMethod->url->link('checkout/checkout', '', true)
            ]
        ];
    }

    /**
     * @param $paymentMethod
     * @return string
     */
    public function getCallbackUrl($paymentMethod)
    {
        return htmlspecialchars_decode(
            $paymentMethod->url->link(
                'extension/payment/'.$this->paymentMethod.'/callback',
                ['order_id' => $paymentMethod->request->get['order_id']]
            )
        );
    }

    /**
     * @param $paymentMethod
     * @return string
     */
    public function getProcessingUrl($paymentMethod)
    {
        return htmlspecialchars_decode(
            $paymentMethod->url->link(
                'extension/payment/'.$this->paymentMethod.'/processing',
                ['order_id' => $paymentMethod->request->get['order_id']]
            )
        );
    }

    /**
     * @param $paymentMethod
     * @return string
     */
    public function getPendingUrl($paymentMethod)
    {
        return htmlspecialchars_decode(
            $paymentMethod->url->link(
                'extension/payment/'.$this->paymentMethod.'/pending',
                ['order_id' => $paymentMethod->request->get['order_id']]
            )
        );
    }

    /**
     * @param $paymentMethod
     * @return array
     */
    public function getOrderLines($orderInfo, $paymentMethod, $totalAmountInCents)
    {
        $total_amount = 0;
        $paymentMethod->load->model('tool/image');
        $orderLines = [];

        foreach ($paymentMethod->cart->getProducts() as $item) {
            $amount = static::formatAmountToCents(
                    $paymentMethod->tax->calculate(
                        $item['price'],
                        $item['tax_class_id'],
                        true
                    )
                );
            $orderLines[] = array_filter([
		        'url' => $paymentMethod->url->link('product/product', 'product_id='.$item['product_id']),
		        'name' => $item['name'],
		        'type' => 'physical',
		        'amount' => $amount,
		        'currency' => 'EUR',
		        'quantity' => $item['quantity'],
		        'image_url' => $paymentMethod->model_tool_image->resize($item['image'], 100, 100),
		        'vat_percentage' => $this->getOrderLineTaxRate($paymentMethod, $item['price'], $item['tax_class_id']),
		        'merchant_order_line_id' => $item['product_id']
	        ],
	        function($value) {
		        return !is_null($value);
	        });
            $total_amount += $amount * $item['quantity'];
        }

        if (array_key_exists('shipping_method', $paymentMethod->session->data)
            && intval($paymentMethod->session->data['shipping_method']['cost']) > 0
        ) {
            $shipping_costs = $this->getShippingOrderLine($paymentMethod);
            $orderLines[] = $shipping_costs;
            $total_amount += $shipping_costs['amount'];
        }

        if (($totalAmountInCents - $total_amount) != 0) {
            $orderLines[] = [
                'name' => 'Overig',
                'type' => 'physical',
                'amount' => $totalAmountInCents - $total_amount,
                'currency' => 'EUR',
                'quantity' => 1,
                'vat_percentage' => 2100,
                'merchant_order_line_id' => 'miscellaneous',
            ];
        }
        return $orderLines;
    }

    /**
     * @param $paymentMethod
     * @return array
     */
    public function getShippingOrderLine($paymentMethod)
    {
        $shippingMethod = $paymentMethod->session->data['shipping_method'];

        return [
            'name' => $shippingMethod['title'],
            'type' => 'shipping_fee',
            'amount' => static::formatAmountToCents(
                $paymentMethod->tax->calculate(
                    $shippingMethod['cost'],
                    $shippingMethod['tax_class_id'],
                    true
                )
            ),
            'currency' => 'EUR',
            'vat_percentage' => $this->getOrderLineTaxRate(
                $paymentMethod,
                $shippingMethod['cost'],
                $shippingMethod['tax_class_id']
            ),
            'quantity' => 1,
            'merchant_order_line_id' => (count($paymentMethod->cart->getProducts()) + 1)
        ];
    }

    /**
     * @param $paymentMethod
     * @param $price
     * @param $taxClassId
     * @return int|null
     */
    public function getOrderLineTaxRate($paymentMethod, $price, $taxClassId)
    {
        $taxRate = 0;
        $appliedTaxRates = $paymentMethod->tax->getRates($price, $taxClassId);

        if (count($appliedTaxRates) > 0) {
            foreach ($appliedTaxRates as $appliedTaxRate) {
                $taxRate += $appliedTaxRate['rate'];
            }
        }

        return static::formatAmountToCents($taxRate);
    }

    /**
     * @param $ipList
     * @return bool
     */
    public static function ipIsEnabled($ipList)
    {
        if (strlen($ipList) > 0) {
            $ipWhitelist = array_map('trim', explode(',', $ipList));

            if (!in_array(filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP), $ipWhitelist)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string
     */
    public static function getPluginVersion()
    {
        return sprintf('OpenCart v%s', self::PLUGIN_VERSION);
    }

    /**
     * Obtain EMS Online order id from order history.
     *
     * @param array $orderHistory
     * @return mixed
     */
    public static function searchHistoryForOrderKey(array $orderHistory)
    {
        foreach ($orderHistory as $history) {
            preg_match('/\w{8}-\w{4}-\w{4}-\w{4}-\w{12}/', $history['comment'], $orderKey);
            if (count($orderKey) > 0) {
                return $orderKey[0];
            }
        }
    }
}
