<?php
	function isPagerdutyAlertOpen () {
		require 'config.php';

		$url = 'https://telusdigital.pagerduty.com';
		$path = '/api/v1/incidents';
		$params = '?status=triggered,acknowledged';
		$context = stream_context_create(array(
			'http' => array(
				'header'  => "Authorization: Token token=$pagerduty_api_accesskey"
			)
		));

		$json = json_decode($useCache ? $cache_incidents : file_get_contents($url . $path . $params, false, $context), true);

		foreach ($json["incidents"] as $incident) {
			if (strpos($incident["service"]["name"], 'An Error Has Occurred') !== false) {
				return true;
			}
		}

		return false;
	}

	function alertPagerduty ($value, $message) {
		require 'config.php';

		$data = <<<EOD
{
	"service_key": "$pagerduty_servicekey",
	"event_type": "trigger",
	"description": "Failed: threshold ($alert_threshold) exceeded with $value issues in the last hour",
	"client": "omniture",
	"client_url": "https://sc2.omniture.com/sc15/reports/index.html?a=RealTime&jpj=32978072442780#/overview?search=an%20error%20has&timeRange=2",
	"details": {
		"type": "threshold_exceeded",
		"threshold": "$alert_threshold",
		"value": "$value",
		"details": "$message"
	}
}
EOD;
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, 'https://events.pagerduty.com/generic/2010-04-15/create_event.json');
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json'
		));

		echo $head = curl_exec($curl);
	}

	function PagerDutyGetIncidentKey () {
		require 'config.php';
		$url = 'https://telusdigital.pagerduty.com';
		$path = '/api/v1/incidents';
		$params = '?status=triggered,acknowledged';
		$context = stream_context_create(array(
			'http' => array(
				'header'  => "Authorization: Token token=$pagerduty_api_accesskey"
			)
		));

		$json = json_decode($useCache ? $cache_incidents : file_get_contents($url . $path . $params, false, $context), true);

		foreach ($json["incidents"] as $incident) {
			if (strpos($incident["service"]["name"], 'An Error Has Occurred') !== false) {
				return $incident["incident_number"];
			}
		}

		return false;
	}
	function PagerDutyAlertClose () {
		require 'config.php';
		$incident_key = PagerDutyGetIncidentKey();

		$data = <<<EOD
{
	"service_key": "$pagerduty_servicekey",
	"event_type": "resolve",
	"incident_key": "$incident_key",
	"details": {
		"fixed at": "2010-06-10 06:00"
	}
}
EOD;
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, 'https://events.pagerduty.com/generic/2010-04-15/create_event.json');
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json'
		));

		echo $head = curl_exec($curl);
	}