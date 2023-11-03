<?php
class ControllerExtensionPaymentStripe extends Controller {
	private $error = array();

	public function index() {
		$this->load->model('setting/setting');

		$this->load->model('extension/payment/stripe');
		$this->load->language('extension/payment/stripe');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_stripe', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/stripe', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['currencies'] = [
			'usd',
			'eur'
		];

		if($this->initStripe() == true) {
			$data['currencies'] = \Stripe\CountrySpec::retrieve("US")['supported_payment_currencies'];
		}

		$data['action'] = $this->url->link('extension/payment/stripe', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

		if (isset($this->request->post['payment_stripe_environment'])) {
			$data['stripe_environment'] = $this->request->post['payment_stripe_environment'];
		} elseif ($this->config->has('payment_stripe_environment')) {
			$data['stripe_environment'] = $this->config->get('payment_stripe_environment');
		} else {
			$data['stripe_environment'] = 'test';
		}

		if (isset($this->request->post['payment_stripe_currency'])) {
			$data['stripe_currency'] = $this->request->post['payment_stripe_currency'];
		} elseif ($this->config->has('payment_stripe_currency')) {
			$data['stripe_currency'] = $this->config->get('payment_stripe_currency');
		} else {
			$data['stripe_currency'] = 'usd';
		}

		if (isset($this->request->post['payment_stripe_test_publishable_key'])) {
			$data['stripe_test_publishable_key'] = $this->request->post['payment_stripe_test_publishable_key'];
		} elseif ($this->config->has('payment_stripe_test_publishable_key')) {
			$data['stripe_test_publishable_key'] = $this->config->get('payment_stripe_test_publishable_key');
		} else {
			$data['stripe_test_publishable_key'] = '';
		}

		if (isset($this->request->post['payment_stripe_test_secret_key'])) {
			$data['stripe_test_secret_key'] = $this->request->post['payment_stripe_test_secret_key'];
		} elseif ($this->config->has('payment_stripe_test_secret_key')) {
			$data['stripe_test_secret_key'] = $this->config->get('payment_stripe_test_secret_key');
		} else {
			$data['stripe_test_secret_key'] = '';
		}

		if (isset($this->request->post['payment_stripe_live_publishable_key'])) {
			$data['stripe_live_publishable_key'] = $this->request->post['payment_stripe_live_publishable_key'];
		} elseif ($this->config->has('payment_stripe_live_publishable_key')) {
			$data['stripe_live_publishable_key'] = $this->config->get('payment_stripe_live_publishable_key');
		} else {
			$data['stripe_live_publishable_key'] = '';
		}

		if (isset($this->request->post['payment_stripe_live_secret_key'])) {
			$data['stripe_live_secret_key'] = $this->request->post['payment_stripe_live_secret_key'];
		} elseif ($this->config->has('payment_stripe_live_secret_key')) {
			$data['stripe_live_secret_key'] = $this->config->get('payment_stripe_live_secret_key');
		} else {
			$data['stripe_live_secret_key'] = '';
		}

		if (isset($this->request->post['payment_stripe_store_cards'])) {
			$data['stripe_store_cards'] = $this->request->post['payment_stripe_store_cards'];
		} elseif ($this->config->has('payment_stripe_store_cards')) {
			$data['stripe_store_cards'] = $this->config->get('payment_stripe_store_cards');
		} else {
			$data['stripe_store_cards'] = 0;
		}

		if (isset($this->request->post['payment_stripe_status'])) {
			$data['stripe_status'] = $this->request->post['payment_stripe_status'];
		} elseif ($this->config->has('payment_stripe_status')) {
			$data['stripe_status'] = $this->config->get('payment_stripe_status');
		}

		if (isset($this->request->post['payment_stripe_order_status_id'])) {
			$data['stripe_order_status_id'] = $this->request->post['payment_stripe_order_status_id'];
		} else {
			$data['stripe_order_status_id'] = $this->config->get('payment_stripe_order_status_id');
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/stripe', $data));
	}

	public function install() {
		if ($this->user->hasPermission('modify', 'extension/payment/stripe')) {
			$this->load->model('extension/payment/stripe');

			$this->model_extension_payment_stripe->install();
		}
	}

	public function uninstall() {
		if ($this->user->hasPermission('modify', 'extension/payment/stripe')) {
			$this->load->model('extension/payment/stripe');

			$this->model_extension_payment_stripe->uninstall();
		}
	}

	public function refund() {
		$this->load->language('extension/payment/stripe');
		$this->initStripe();

		$json = array();
		$json['error'] = false;

		if (isset($this->request->post['order_id']) && $this->request->post['order_id'] != '') {
			$this->load->model('extension/payment/stripe');
			$this->load->model('user/user');

			$stripe_order = $this->model_extension_payment_stripe->getOrder($this->request->post['order_id']);
			$user_info = $this->model_user_user->getUser($this->user->getId());

			$re = \Stripe\Refund::create(array(
			  "charge" => $stripe_order['stripe_order_id'],
			  "amount" => $this->request->post['amount'] * 100,
			  "metadata" => array(
			  	"opencart_user_username" => $user_info['username'],
			  	"opencart_user_id" => $this->user->getId()
			  )
			));

		} else {
			$json['error'] = true;
			$json['msg'] = 'Missing data';
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function order() {

		if ($this->config->get('payment_stripe_status')) {
			$this->load->model('extension/payment/stripe');
			$this->load->language('extension/payment/stripe');

			$data['order_id'] = $this->request->get['order_id'];

			$stripe_order = $this->model_extension_payment_stripe->getOrder($this->request->get['order_id']);

			if ($stripe_order && $this->initStripe()) {
				$data['stripe_environment'] = $stripe_order['environment'];

				$data['charge'] = \Stripe\Charge::retrieve($stripe_order['stripe_order_id']);
				$data['transaction'] = \Stripe\BalanceTransaction::retrieve($data['charge']['balance_transaction']);

				$data['user_token'] = $this->request->get['user_token'];

				return $this->load->view('extension/payment/stripe_order', $data);
			}
		}
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/stripe')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_warning');
		}

		return !$this->error;
	}

	private function initStripe() {
		$this->load->library('stripe');
		if($this->config->get('payment_stripe_environment') == 'live') {
			$stripe_secret_key = $this->config->get('payment_stripe_live_secret_key');
		} else {
			$stripe_secret_key = $this->config->get('payment_stripe_test_secret_key');
		}

		if($stripe_secret_key != '' && $stripe_secret_key != null) {
			\Stripe\Stripe::setApiKey($stripe_secret_key);
			return true;
		}

		return false;

	}
}
