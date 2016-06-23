<?php
	require 'config.php';
	require_once 'lib-pagerduty.php';

	$created = gmdate('Y-m-dTH:i:s') . "Z";
	$nonce = md5(rand(), true);
	$base64_nonce = base64_encode($nonce);
	$password_digest = base64_encode(sha1($nonce.$created.$password, true));
	$data = <<<EOD
{
    "reportDescription": {
        "source": "realtime",
        "reportSuiteID": "$reportSuiteID",
        "metrics": [
            {
            	"id": "instances"
            }
        ],
        "elements": [
			{
				"id": "page",
				"search":
					{
						"keywords": [
							"an error has occurred"
						]
					}
			}
		]
	}
}
EOD;

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, 'https://api.omniture.com/admin/1.4/rest/?method=Report.Run');
	curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	curl_setopt($curl, CURLOPT_HEADER, 0);
	curl_setopt($curl, CURLOPT_POST, 1);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json',
		'Content-Length: ' . strlen($data),
		sprintf('X-WSSE: UsernameToken Username="%s", PasswordDigest="%s", Nonce="%s", Created="%s"', $username, $password_digest, $base64_nonce, $created),
	));

	$head = $useCache ? $cache_realtime_normal : curl_exec($curl);
	$json = json_decode($head, true);

	$data = $json['report']['data'];

	$mined_data = array ();
	
	foreach ($data as $datapoint) {
		foreach ($datapoint['breakdown'] as $breakdownpoint) {
			$name = explode ('?', $breakdownpoint['name'])[0];
			$trend = $breakdownpoint['trend'];
			$counts = $breakdownpoint['counts'][0];

			if (isset ($mined_data[$name])) {
				$mined_data[$name] += $counts;
			} else {
				$mined_data[$name] = $counts;
			}
		}
	}

	$total = 0;
	$buffered_message = '';
	foreach ($mined_data as $key => $value) {
		$buffered_message .= "$key($value) ";
		$total += $value;
	}
	$threshold_passed = $total >= $alert_threshold;

	echo "Total errors: $total";

	$isPagerdutyAlertOpen = isPagerdutyAlertOpen();
	if ($threshold_passed && !$isPagerdutyAlertOpen) {
		echo '(Alerting)';
		alertPagerduty ($total, $buffered_message);
	} else if ($threshold_passed) {
		echo '(Alert)';
	} else if (!$threshold_passed && $isPagerdutyAlertOpen) {
		echo '(Stopping Alert)';
		PagerDutyAlertClose ();
	}
