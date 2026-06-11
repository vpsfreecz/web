<?php
require_once __DIR__.'/../../lib/form.php';

$failures = 0;

function fail_test($message) {
	global $failures;

	$failures++;
	fwrite(STDERR, $message."\n");
}

function validator_errors($lang, $field, $value, $mxResolver = null, $data = array()) {
	$v = new Validators($lang, array($field), $mxResolver);
	$v->validate(array_merge($data, array($field => $value)));

	return $v->errors[$field] ?? array();
}

function expect_errors($label, $lang, $field, $value, $expected, $mxResolver = null, $data = array()) {
	$actual = validator_errors($lang, $field, $value, $mxResolver, $data);
	sort($actual);
	sort($expected);

	if ($actual !== $expected)
		fail_test($label.': expected '.json_encode($expected).', got '.json_encode($actual));
}

function expect_valid($label, $lang, $field, $value, $mxResolver = null, $data = array()) {
	expect_errors($label, $lang, $field, $value, array(), $mxResolver, $data);
}

$year = intval(date('Y'));
$noMx = function ($domain) {
	return false;
};
$hotmailMx = function ($domain) {
	return array(array('target' => 'mx1.hotmail.com'));
};

expect_valid('login accepts short login', 'en', 'login', 'aaa');
expect_valid('login accepts dash and dot', 'en', 'login', 'aa-bb.cc');
expect_valid('login accepts max length', 'en', 'login', str_repeat('abc', 21));
expect_errors('login rejects too short', 'en', 'login', 'aa', array('NOTLOGIN'));
expect_errors('login rejects spaces', 'en', 'login', 'bad login', array('NOTLOGIN'));
expect_errors('login rejects two dots', 'en', 'login', 'a..b', array('TWODOTS'));
expect_errors('login rejects two hyphens', 'en', 'login', 'a--b', array('TWOHYPHENS'));
expect_errors('login rejects too long', 'en', 'login', str_repeat('abc', 22), array('NOTLOGIN'));
expect_errors('login rejects repeated characters', 'en', 'login', 'aaaa', array('RANDOMTEXT'));

expect_valid('name accepts non-ascii', 'cs', 'name', 'Zlutoucky Kun');
expect_valid('name accepts cjk', 'en', 'name', 'Tokyo User');
expect_errors('name rejects one character', 'en', 'name', 'A', array('LEN_5', 'FULLNAME'));
expect_errors('name rejects digits', 'en', 'name', 'John Doe3', array('NOTNUM'));
expect_errors('name rejects single word', 'en', 'name', 'Tokyo', array('FULLNAME'));

expect_valid('email accepts syntax without mx', 'en', 'email', 'person@example.test', $noMx);
expect_errors('email rejects bad syntax', 'en', 'email', 'person', array('NOTMAIL'), $noMx);
expect_errors('email rejects whitespace', 'en', 'email', 'person @example.test', array('NOTMAIL'), $noMx);
expect_errors('email rejects hotmail mx', 'en', 'email', 'person@example.test', array('EHOTMAIL'), $hotmailMx);

expect_valid('birth accepts adult', 'en', 'birth', (string)($year - 30));
expect_errors('birth rejects non-numeric', 'en', 'birth', 'abcd', array('NUMONLY'));
expect_errors('birth rejects too young', 'en', 'birth', (string)($year - 5), array('NOTBIRTH'));
expect_errors('birth rejects too old', 'en', 'birth', (string)($year - 101), array('NOTBIRTH'));

expect_valid('cs address accepts house number', 'cs', 'address', 'Street 12');
expect_errors('cs address requires house number', 'cs', 'address', 'Street', array('NOHOUSEN'));
expect_errors('cs address rejects one character', 'cs', 'address', 'A', array('LEN_5', 'NOHOUSEN'));
expect_valid('en address accepts no house number', 'en', 'address', 'Street');
expect_errors('en address rejects one character', 'en', 'address', 'A', array('LEN_5'));

expect_valid('city accepts normal value', 'en', 'city', 'Prague');
expect_valid('city accepts district number', 'cs', 'city', 'Praha 6');
expect_errors('city rejects one character', 'en', 'city', 'A', array('LEN_2'));
expect_errors('city rejects repeated characters', 'en', 'city', 'aaaa', array('RANDOMTEXT'));

expect_valid('cs zip accepts spaces', 'cs', 'zip', '120 00', null, array('country' => 'Cesko'));
expect_errors('cs zip rejects empty', 'cs', 'zip', '', array('NOTEMPTY'), null, array('country' => 'Cesko'));
expect_errors('cs zip rejects letters', 'cs', 'zip', 'abc', array('LEN_5_EXACT', 'NUMONLY'), null, array('country' => 'Cesko'));
expect_valid('uk zip accepts alphanumeric', 'en', 'zip', 'SW1A 1AA', null, array('country' => 'United Kingdom'));
expect_errors('uganda calling code is not postal code', 'en', 'zip', '256', array('POSTALCODE'), null, array('country' => 'Uganda'));
expect_valid('uganda accepts n/a postal code', 'en', 'zip', 'N/A', null, array('country' => 'Uganda'));
expect_errors('en zip rejects empty', 'en', 'zip', '', array('NOTEMPTY'));

expect_valid('country accepts normal value', 'cs', 'country', 'Cesko');
expect_errors('country rejects one character', 'en', 'country', 'A', array('LEN_2'));
expect_errors('country rejects digits', 'en', 'country', 'CZ1', array('NOTNUM'));

expect_valid('org name accepts three characters', 'en', 'org_name', 'ABC');
expect_errors('org name rejects short value', 'en', 'org_name', 'AB', array('LEN_3'));

expect_valid('org id accepts spaces', 'cs', 'ic', '123 456');
expect_errors('org id rejects short value', 'cs', 'ic', '12345', array('LEN_6'));
expect_errors('org id rejects letters', 'cs', 'ic', '12345a', array('NUMONLY'));

foreach (array('distribution', 'location', 'currency') as $field) {
	expect_valid($field.' accepts selected value', 'en', $field, '1');
	expect_errors($field.' rejects empty value', 'en', $field, '', array('NOTSELECTED'));
}

expect_valid('time zone accepts empty value', 'en', 'time_zone', '');
expect_valid('time zone accepts valid identifier', 'en', 'time_zone', 'Europe/Prague');
expect_errors('time zone rejects invalid identifier', 'en', 'time_zone', 'Invalid/Zone', array('NOTSELECTED'));

if ($failures > 0)
	exit(1);

echo "registration validator tests passed\n";
