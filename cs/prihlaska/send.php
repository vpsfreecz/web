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

$f = new RegistrationForm('cs', $db, $_POST);
$f->validate();

if (!$f->isValid()) {
	virtual('/prihlaska/hlavicka.html');
	echo '<form action="/prihlaska/send.php" method="post">';

	foreach ($f->getErrors() as $field => $errors) {
		echo '<div class="alert alert-danger" role="alert">';
		echo $f->getLabel($field).': '.implode('; ', $errors);
		echo '</div>';
	}

	echo '<input type="hidden" name="entity_type" value="'.$_POST['entity_type'].'">';
	include 'fyzicka-osoba/form.php';

	echo '</form>';
	virtual('/prihlaska/paticka.html');
	exit;
}

$registration = new Registration('cs', $db, $f->getData());
$registration->register();

// TODO: error checking?

header('Location: /prihlaska/prijata/');
