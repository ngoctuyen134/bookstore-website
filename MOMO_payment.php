<?php
if (!defined('ABSPATH')) exit;

class WC_Gateway_MOMO extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'momo';
        $this->method_title = __('MoMo Payment Gateway', 'woocommerce');
        $this->method_description = 'Thanh toán bằng ví MoMo';
        $this->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title        = $this->get_option('title');
        $this->partner_code = $this->get_option('partner_code');
        $this->access_key   = $this->get_option('access_key');
        $this->secret_key   = $this->get_option('secret_key');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);


        // $this->id = 'momo';
        // $this->method_title = 'MoMo';
        // $this->method_description = 'Thanh toán qua ví MoMo';
        // $this->has_fields = false;

        // $this->init_form_fields();
        // $this->init_settings();

        // $this->title = $this->get_option('title');
        // $this->partner_code = $this->get_option('partner_code');
        // $this->access_key = $this->get_option('access_key');
        // $this->secret_key = $this->get_option('secret_key');
        // $this->return_url = $this->get_option('return_url');
        // $this->notify_url = $this->get_option('notify_url');

        // add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Bật/Tắt',
                'type' => 'checkbox',
                'label' => 'Bật MoMo Gateway',
                'default' => 'yes'
            ),
            'title' => array(
                'title' => 'Tiêu đề hiển thị',
                'type' => 'text',
                'default' => 'Thanh toán qua MoMo'
            ),
            'partner_code' => array(
                'title' => 'Partner Code',
                'type' => 'text'
            ),
            'access_key' => array(
                'title' => 'Access Key',
                'type' => 'text'
            ),
            'secret_key' => array(
                'title' => 'Secret Key',
                'type' => 'text'
            )
        );
    }

    public function process_payment($order_id) {
        // $order = wc_get_order($order_id);
        // $endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";

        // $requestId = time() . "";
        // $orderInfo = "Thanh toán đơn hàng #" . $order_id;
        // $amount = (string) $order->get_total();
        // $orderId = $order_id . '-' . time();
        // $extraData = "";

        // $rawHash = "accessKey=" . $this->access_key .
        //     "&amount=" . $amount .
        //     "&extraData=" . $extraData .
        //     "&ipnUrl=" . $this->notify_url .
        //     "&orderId=" . $orderId .
        //     "&orderInfo=" . $orderInfo .
        //     "&partnerCode=" . $this->partner_code .
        //     "&redirectUrl=" . $this->return_url .
        //     "&requestId=" . $requestId .
        //     "&requestType=captureWallet";

        // $signature = hash_hmac("sha256", $rawHash, $this->secret_key);

        // $data = array(
        //     'partnerCode' => $this->partner_code,
        //     'accessKey' => $this->access_key,
        //     'requestId' => $requestId,
        //     'amount' => $amount,
        //     'orderId' => $orderId,
        //     'orderInfo' => $orderInfo,
        //     'redirectUrl' => $this->return_url,
        //     'ipnUrl' => $this->notify_url,
        //     'extraData' => $extraData,
        //     'requestType' => "captureWallet",
        //     'signature' => $signature,
        //     'lang' => 'vi'
        // );

        // $args = array(
        //     'body' => json_encode($data),
        //     'headers' => array('Content-Type' => 'application/json'),
        //     'timeout' => 45
        // );

        // $response = wp_remote_post($endpoint, $args);
        // $body = json_decode(wp_remote_retrieve_body($response), true);

        // if (isset($body['payUrl'])) {
        //     return array(
        //         'result' => 'success',
        //         'redirect' => $body['payUrl']
        //     );
        // } else {
        //     wc_add_notice('Không thể tạo liên kết thanh toán MoMo.', 'error');
        //     return array('result' => 'fail');
        // }


        $order = wc_get_order($order_id);
            $amount = (int) $order->get_total();

            if ($amount < 1000) {
                wc_add_notice('Số tiền thanh toán qua MoMo phải từ 1.000 VND.', 'error');
                return ['result' => 'fail'];
            }

            $requestId = uniqid();
            $orderId = 'ORDER' . time();
            $redirectUrl = add_query_arg('momo_return', '1', home_url('/'));
            $ipnUrl = add_query_arg('momo_ipn', '1', home_url('/'));
            $orderInfo = 'Thanh toán đơn hàng #' . $order_id;
            $extraData = '';
            $requestType = 'payWithATM';

            $rawSignature = "accessKey={$this->access_key}&amount={$amount}&extraData={$extraData}&ipnUrl={$ipnUrl}&orderId={$orderId}&orderInfo={$orderInfo}&partnerCode={$this->partner_code}&redirectUrl={$redirectUrl}&requestId={$requestId}&requestType={$requestType}";
            $signature = hash_hmac("sha256", $rawSignature, $this->secret_key);

            $data = [
                "partnerCode" => $this->partner_code,
                "accessKey" => $this->access_key,
                "requestId" => $requestId,
                "amount" => $amount,
                "orderId" => $orderId,
                "orderInfo" => $orderInfo,
                "redirectUrl" => $redirectUrl,
                "ipnUrl" => $ipnUrl,
                "extraData" => $extraData,
                "requestType" => $requestType,
                "signature" => $signature,
                "lang" => "vi"
            ];

            $response = wp_remote_post('https://test-payment.momo.vn/v2/gateway/api/create', [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($data)
            ]);

            if (is_wp_error($response)) {
                wc_add_notice('Không thể kết nối đến MoMo: ' . $response->get_error_message(), 'error');
                return ['result' => 'fail'];
            }

            $result = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($result['payUrl'])) {
                $order->update_status('pending', 'Chờ thanh toán qua MoMo');
                return [
                    'result' => 'success',
                    'redirect' => $result['payUrl']
                ];
            } else {
                wc_add_notice('Lỗi khi tạo đơn hàng MoMo: ' . json_encode($result, JSON_UNESCAPED_UNICODE), 'error');
                return ['result' => 'fail'];
            }
    }
}