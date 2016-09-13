<?php

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
	http_response_code(400);
	exit;
}

require_once '../../config.php';
require_once '../../lib/db.lib.php';
require_once '../../lib/form.php';
require_once '../../lib/register.php';

$db = new sql_db (DB_HOST, DB_USER, DB_PASS, DB_NAME);

$f = new RegistrationForm('en', $db, $_POST);
$f->validate();

if (!$f->isValid()) {
	$f->printErrors();
	exit;
}

if ($f->isValidationTest()) {
	header('Location: /registration/accepted/');
	exit;
}

$registration = new Registration('cs', $db, $f->getData());

if (!$registration->register()) {
	$f->printErrors(array('EFAILED'));
	exit;
}

header('Location: /registration/accepted/');
