<?php
if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', 'init_vnpay_gateway');

function init_vnpay_gateway() {
    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_Gateway_VNPAY extends WC_Payment_Gateway {

        public function __construct() {
            $this->id = 'vnpay';
            $this->method_title = 'VNPay Gateway';
            $this->method_description = 'Thanh toán qua cổng VNPay';
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->vnp_TmnCode = 'NJJ0R8FS'; // Terminal ID
            $this->vnp_HashSecret = 'BYKJBHPPZKQMKBIBGGXIYKWYFAYSJXCW'; // Secret Key
            $this->vnp_Url = 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'; // VNPay URL
            $this->vnp_ReturnUrl = $this->get_option('vnp_ReturnUrl');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            //add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'vnpay_return_handler'));
			add_action('init', [$this, 'register_vnpay_endpoint']);
			add_action('template_redirect', [$this, 'vnpay_check_response']);
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Bật/Tắt',
                    'type' => 'checkbox',
                    'label' => 'Bật cổng thanh toán VNPay',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => 'Tiêu đề',
                    'type' => 'text',
                    'default' => 'Thanh toán qua VNPay'
                ),
            );
        }

        public function process_payment($order_id) {
			date_default_timezone_set('Asia/Ho_Chi_Minh');
            $order = wc_get_order($order_id);
            $vnp_TmnCode = $this->vnp_TmnCode;
            $vnp_HashSecret = $this->vnp_HashSecret;
            $vnp_Url = $this->vnp_Url;
            $vnp_Returnurl = $order->get_checkout_order_received_url();

			$startTime = date("YmdHis");
        	$expire = date('YmdHis', strtotime('+15 minutes', strtotime($startTime)));
			
            $vnp_TxnRef = $order->get_id();
            $vnp_OrderInfo = 'Thanh toán đơn hàng #' . $order->get_id();
			$vnp_OrderType = 'billpayment';
            $vnp_Amount = $order->get_total() * 100; // VNPay yêu cầu số tiền tính bằng đồng
            $vnp_Locale = 'vn';
			$vnp_BankCode = 'NCB';
            $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
			$vnp_ExpireDate = $expire;
			
            $inputData = array(
                "vnp_Version" => "2.1.0",
                "vnp_TmnCode" => $vnp_TmnCode,
                "vnp_Amount" => $vnp_Amount,
                "vnp_Command" => "pay",
                "vnp_CreateDate" => date('YmdHis'),
                "vnp_CurrCode" => "VND",
                "vnp_IpAddr" => $vnp_IpAddr,
                "vnp_Locale" => $vnp_Locale,
                "vnp_OrderInfo" => $vnp_OrderInfo,
				"vnp_OrderType" => $vnp_OrderType,
                "vnp_ReturnUrl" => $vnp_Returnurl,
                "vnp_TxnRef" => $vnp_TxnRef,
				"vnp_ExpireDate" => $vnp_ExpireDate
            );
			
			if (isset($vnp_BankCode) && $vnp_BankCode != "") {
				$inputData['vnp_BankCode'] = $vnp_BankCode;
			}

            ksort($inputData);
			$query = "";
			$i = 0;
			$hashdata = "";
			foreach ($inputData as $key => $value) {
				if ($i == 1) {
					$hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
				} else {
					$hashdata .= urlencode($key) . "=" . urlencode($value);
					$i = 1;
				}
				$query .= urlencode($key) . "=" . urlencode($value) . '&';
			}

            $vnp_Url = $vnp_Url . "?" . $query;
			if (isset($vnp_HashSecret)) {
				$vnpSecureHash =   hash_hmac('sha512', $hashdata, $vnp_HashSecret); 
				$vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
			}
			
			return array(
				'result' => 'success',
				'redirect' => $vnp_Url
			);
        }
		
		public function register_vnpay_endpoint() {
			add_rewrite_rule('^vnpay-return/?', 'index.php?vnpay_return=1', 'top');
			add_rewrite_tag('%vnpay_return%', '1');
		}

	public function vnpay_check_response() {
		if (get_query_var('vnpay_return') != '1' || !isset($_GET['vnp_SecureHash'])) {
			return;
		}

		$inputData = [];
		foreach ($_GET as $key => $value) {
			if (substr($key, 0, 4) == "vnp_") {
				$inputData[$key] = $value;
			}
		}

		$vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';
		unset($inputData['vnp_SecureHash']);
		unset($inputData['vnp_SecureHashType']);

		ksort($inputData);
		$hashData = '';
		$i = 0;
		foreach ($inputData as $key => $value) {
			if ($i == 1) {
				$hashData .= '&' . urlencode($key) . "=" . urlencode($value);
			} else {
				$hashData .= urlencode($key) . "=" . urlencode($value);
				$i = 1;
			}
		}

		$secureHash = hash_hmac('sha512', $hashData, $this->vnp_HashSecret);

		$order_id = absint($_GET['order_id'] ?? 0);
		$order_key = sanitize_text_field($_GET['order_key'] ?? '');
		$order = wc_get_order($order_id);

		if (!$order || $order->get_order_key() !== $order_key) {
			wp_die("Đơn hàng không hợp lệ", "Lỗi", ['response' => 400]);
		}

		if ($secureHash === $vnp_SecureHash) {
			if ($inputData['vnp_ResponseCode'] == '00') {
				if (!in_array($order->get_status(), ['processing', 'completed'])) {
					$order->payment_complete();
					$order->add_order_note('Thanh toán VNPay thành công. Mã GD: ' . ($inputData['vnp_TransactionNo'] ?? ''));
				}

				if (WC()->cart) {
					WC()->cart->empty_cart();
				}

				wp_safe_redirect($order->get_checkout_order_received_url());
				exit;
			} else {
				$order->add_order_note('Thanh toán VNPay thất bại. Mã lỗi: ' . $inputData['vnp_ResponseCode']);
				wp_safe_redirect(wc_get_page_permalink('cart'));
				exit;
			}
		} else {
			wp_die("Dữ liệu không hợp lệ (chữ ký không khớp)", "Lỗi", ['response' => 400]);
		}
	}

}
		
    add_filter('woocommerce_payment_gateways', function($methods) {
        $methods[] = 'WC_Gateway_VNPAY';
        return $methods;
    });
}
