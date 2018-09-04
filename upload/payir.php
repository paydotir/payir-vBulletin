<?php

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

if (extension_loaded('curl')) {

	$params = array(

		'api'          => $_POST['apikey'],
		'amount'       => $_POST['amount'],
		'redirect'     => urlencode($_POST['callback_url']),
		'factorNumber' => NULL
	);

	$result = common('https://pay.ir/payment/send', $params);

	if ($result && isset($result->status) && $result->status == 1) {

		header('Location: https://pay.ir/payment/gateway/' . $result->transId);

	} else {

		$message = 'در ارتباط با وب سرویس Pay.ir خطایی رخ داده است';
		$message = isset($result->errorMessage) ? $result->errorMessage : $message;

		die($message);
	}

} else {

	die('تابع cURL در سرور فعال نمی باشد');
}
