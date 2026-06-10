<?php
require_once __DIR__.'/../../lib/form.php';

$failures = 0;

function fail_test($message) {
	global $failures;

	$failures++;
	fwrite(STDERR, $message."\n");
}

function validator_errors($lang, $field, $value, $mxResolver = null) {
	$v = new Validators($lang, array($field), $mxResolver);
	$v->validate(array($field => $value));

	return $v->errors[$field] ?? array();
}

function expect_errors($label, $lang, $field, $value, $expected, $mxResolver = null) {
	$actual = validator_errors($lang, $field, $value, $mxResolver);
	sort($actual);
	sort($expected);

	if ($actual !== $expected)
		fail_test($label.': expected '.json_encode($expected).', got '.json_encode($actual));
}

function expect_valid($label, $lang, $field, $value, $mxResolver = null) {
	expect_errors($label, $lang, $field, $value, array(), $mxResolver);
}

$year = intval(date('Y'));
$noMx = function ($domain) {
	return false;
};
$hotmailMx = function ($domain) {
	return array(array('target' => 'mx1.hotmail.com'));
};

expect_valid('login accepts short login', 'en', 'login', 'aa');
expect_valid('login accepts dash and dot', 'en', 'login', 'aa-bb.cc');
expect_valid('login accepts max length', 'en', 'login', str_repeat('a', 63));
expect_errors('login rejects too short', 'en', 'login', 'a', array('NOTLOGIN'));
expect_errors('login rejects spaces', 'en', 'login', 'bad login', array('NOTLOGIN'));
expect_errors('login rejects two dots', 'en', 'login', 'a..b', array('TWODOTS'));
expect_errors('login rejects two hyphens', 'en', 'login', 'a--b', array('TWOHYPHENS'));
expect_errors('login rejects too long', 'en', 'login', str_repeat('a', 64), array('NOTLOGIN'));

expect_valid('name accepts non-ascii', 'cs', 'name', 'Zlutoucky Kun');
expect_valid('name accepts cjk', 'en', 'name', 'Tokyo');
expect_errors('name rejects one character', 'en', 'name', 'A', array('LEN_2'));
expect_errors('name rejects digits', 'en', 'name', 'John3', array('NOTNUM'));

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
expect_errors('cs address rejects one character', 'cs', 'address', 'A', array('LEN_2', 'NOHOUSEN'));
expect_valid('en address accepts no house number', 'en', 'address', 'Street');
expect_errors('en address rejects one character', 'en', 'address', 'A', array('LEN_2'));

expect_valid('city accepts normal value', 'en', 'city', 'Prague');
expect_valid('city accepts district number', 'cs', 'city', 'Praha 6');
expect_errors('city rejects one character', 'en', 'city', 'A', array('LEN_2'));

expect_valid('cs zip accepts spaces', 'cs', 'zip', '120 00');
expect_errors('cs zip rejects empty', 'cs', 'zip', '', array('NOTEMPTY'));
expect_errors('cs zip rejects letters', 'cs', 'zip', 'abc', array('NUMONLY'));
expect_valid('en zip accepts alphanumeric', 'en', 'zip', 'SW1A 1AA');
expect_errors('en zip rejects empty', 'en', 'zip', '', array('NOTEMPTY'));

expect_valid('country accepts normal value', 'cs', 'country', 'Cesko');
expect_errors('country rejects one character', 'en', 'country', 'A', array('LEN_2'));
expect_errors('country rejects digits', 'en', 'country', 'CZ1', array('NOTNUM'));

expect_valid('org name accepts two characters', 'en', 'org_name', 'AB');
expect_errors('org name rejects one character', 'en', 'org_name', 'A', array('LEN_2'));

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
