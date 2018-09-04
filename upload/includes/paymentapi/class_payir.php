<?php

if (isset($GLOBALS['vbulletin']->db) == FALSE) {

	exit;
}

class vB_PaidSubscriptionMethod_payir extends vB_PaidSubscriptionMethod
{
	var $supports_recurring = FALSE;
	var $display_feedback   = TRUE;

	function verify_payment()
	{
		$this->registry->input->clean_array_gpc('r', array(

			'item'	       => TYPE_STR,
			'status'       => TYPE_STR,
			'transId'	   => TYPE_STR,
			'factorNumber' => TYPE_STR,
			'message'      => TYPE_STR
		));

		if (extension_loaded('curl') == FALSE) {

			$this->error = 'تابع cURL در سرور فعال نمی باشد';
			return FALSE;
		}

		if ($this->test() == FALSE) {

			$this->error = 'افزونه پرداخت پیکربندی نشده است';
			return FALSE;
		}

		$this->transaction_id = $this->registry->GPC['transId'];

		if (empty($this->registry->GPC['item']) == FALSE && isset($this->registry->GPC['status']) && isset($this->registry->GPC['transId']) && isset($this->registry->GPC['factorNumber'])) {

			$status       = $this->registry->GPC['status'];
			$transId      = $this->registry->GPC['transId'];
			$factorNumber = $this->registry->GPC['factorNumber'];
			$message      = $this->registry->GPC['message'];

			$this->paymentinfo = $this->registry->db->query_first("SELECT paymentinfo.*, user.username FROM " . TABLE_PREFIX . "paymentinfo AS paymentinfo INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid) WHERE hash = '" . $this->registry->db->escape_string($this->registry->GPC['item']) . "'");

			if (empty($this->paymentinfo) == FALSE && isset($status) && $status == 1) {

				$sub    = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = " . $this->paymentinfo['subscriptionid']);

				$cost   = unserialize($sub['cost']);
				$amount = floor($cost[0][cost][usd] * $this->settings['d2t']);

				$params = array (

					'api'     => $this->settings['apikey'],
					'transId' => $transId
				);

				$result = self::common('https://pay.ir/payment/verify', $params);

				if ($result && isset($result->status) && $result->status == 1) {

					$cardNumber = isset($this->registry->GPC['cardNumber']) ? $this->registry->GPC['cardNumber'] : 'Null';

					if ($amount == $result->amount) {

						$this->paymentinfo['currency'] = 'usd';
						$this->paymentinfo['amount']   = $cost[0][cost][usd];
						$this->type = 1;

						return TRUE;

					} else {

						$this->error = 'رقم تراكنش با رقم پرداخت شده مطابقت ندارد';
						return FALSE;
					}

				} else {

					$this->error = isset($result->errorMessage) ? $result->errorMessage : 'در ارتباط با وب سرویس Pay.ir و بررسی تراکنش خطایی رخ داده است';
					return FALSE;
				}

			} else {

				$this->error = $message ? $message : 'تراكنش با خطا مواجه شد و یا توسط پرداخت کننده کنسل شده است';
				return FALSE;
			}

		} else {

			$this->error = 'اطلاعات ارسال شده مربوط به تایید تراکنش ناقص و یا غیر معتبر است';
			return FALSE;
		}
    }

	function test()
	{
		if (extension_loaded('curl')) {

			if (empty($this->settings['apikey']) == FALSE AND empty($this->settings['d2t']) == FALSE) {

				return TRUE;
			}
		}

		return FALSE;
	}

	function generate_form_html($hash, $cost, $currency, $subinfo, $userinfo, $timeinfo)
	{
		global $vbphrase, $vbulletin, $show;

		$item   = $hash;
		$cost   = floor($cost * $this->settings['d2t']);
		$apikey = $this->settings['apikey'];

		$form['action'] = 'payir.php';
		$form['method'] = 'POST';

		$settings =& $this->settings;

		$templater = vB_Template::create('subscription_payment_payir');

		$templater->register('apikey', $apikey);
		$templater->register('cost', $cost);
		$templater->register('item', $item);
		$templater->register('subinfo', $subinfo);
		$templater->register('settings', $settings);
		$templater->register('userinfo', $userinfo);

		$form['hiddenfields'] .= $templater->render();

		return $form;
	}

	function common($url, $params)
	{
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

		$response = curl_exec($ch);
		$error    = curl_errno($ch);

		curl_close($ch);

		$output = $error ? FALSE : json_decode($response);

		return $output;
	}
}
