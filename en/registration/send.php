<?php

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
	http_response_code(400);
	exit;
}

require_once __DIR__.'/../../lib/init.php';

$api = new \HaveAPI\Client(API_URL);

$f = new RegistrationForm('en', $_POST);
$f->validate();

if (!$f->isValid()) {
	$f->printErrors();
	exit;
}

if ($f->isValidationTest()) {
	header('Location: /registration/accepted/');
	exit;
}

$registration = new Registration('en', $api, $f->getData());

if (!$registration->register()) {
	$f->printErrors(array('EFAILED'));
	exit;
}

header('Location: /registration/accepted/');
