<?php

class Registration {
	private $lang;
	private $db;
	private $data;

	public function __construct($lang, $api, $data) {
		$this->lang = $lang;
		$this->api = $api;
		$this->data = $data;
		$this->ip = null;
	}

	public function register() {
		$params = array(
			'login' => $this->data['login'],
			'full_name' => $this->data['name'],
			'email' => $this->data['email'],
			'address' => $this->data['address'],
			'year_of_birth' => $this->data['birth'],
			'how' => $this->data['how'],
			'note' => $this->data['note'],
			'os_template' => $this->data['distribution'],
			'location' => $this->data['location'],
			'currency' => $this->data['currency'],
			'language' => $this->getLanguageId($this->lang),
		);
		
		if ($this->data['entity_type'] == 'pravnicka') {
			$params['org_name'] = $this->data['org_name'];
			$params['org_id'] = $this->data['ic'];
		}

		return $this->api->user_request->registration->create($params);
	}

	private function getLanguageId($code) {
		foreach ($this->api->language->list() as $lang) {
			if ($lang->code == $code)
				return $lang->id;
		}

		return 0;
	}

	private function getClientIp() {
		if ($this->ip !== null)
			return $this->ip;

		if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
			$ips = array_values(array_filter(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));

			$this->ip = end($ips);

		} else if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
			$this->ip = $_SERVER["REMOTE_ADDR"];

		} else if (array_key_exists('HTTP_CLIENT_IP', $_SERVER)) {
			$this->ip = $_SERVER["HTTP_CLIENT_IP"];

		} else {
			$this->ip = false;
		}

		return $this->ip;
	}
}
