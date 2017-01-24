<?php

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
	http_response_code(400);
	exit;
}

require_once __DIR__.'/../../lib/init.php';

$api = new \HaveAPI\Client(API_URL);

$f = new RegistrationForm('cs', $_POST);
$f->validate();

if (!$f->isValid()) {
	$f->printErrors();
	exit;
}

if ($f->isValidationTest()) {
	header('Location: /prihlaska/prijata/');
	exit;
}

$registration = new Registration('cs', $api, $f->getData());

try {
	$registration->register();

} catch (\HaveAPI\Client\Exception\ActionFailed $e) {
	$f->printErrors($e->getResponse());
	exit;
}

header('Location: /prihlaska/prijata/');
