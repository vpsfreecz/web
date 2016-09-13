<?php
$TR = array(
	'cs' => array(
		'form' => array(
			'fields' => array(
				'login' => 'Přihlašovací jméno',
				'first_name' => 'Jméno',
				'surname' => 'Příjmení',
				'email' => 'E-mail',
				'birth' => 'Rok narození',
				'address' => 'Ulice, č.p.',
				'zip' => 'PSČ',
				'city' => 'Město',
				'country' => 'Stát',
				'how' => 'Jak ses o nás dozvěděl?',
				'note' => 'Poznámky',
				'distribution' => 'Vyber si distribuci',
				'location' => 'Preferovaná lokace pro VPS',
				'currency' => 'Měna platby členského příspěvku',
				'org_name' => 'Název organizace',
				'ic' => 'IČ',
			),
			'errors' => array(
				'NOTEMPTY' => 'musí být vyplněno',
				'NOTSELECTED' => 'vyber některou z nabízených voleb',
				'LEN_2' => 'musí mít minimálně 2 znaky',
				'LEN_6' => 'musí mít minimálně 6 znaků',
				'NOTNUM' => 'nemůže obsahovat číslo',
				'NUMONLY' => 'může obsahovat pouze číclice 0-9',
				'NOTLOGIN' => 'může obsahovat pouze znaky a-z, A-Z, 0-9, pomlčka a tečka, 2 až 63 znaků',
				'NOTMAIL' => 'není platná e-mailová adresa',
				'NOTBIRTH' => 'nepřijatelný rok narození',
				'NOHOUSEN' => 'chybí číslo popisné',
				'TWODOTS' => 'nesmí obsahovat dvě tečky zasebou',
				'TWOHYPHENS' => 'nesmí obsahovat dvě pomlčky zasebou',
				'EEXISTS' => 'tato hodnota už je použita, zvol prosím jinou',
				'EFAILED' => 'Přihlášku se nepodařilo uložit. Zkus to prosím znovu, případně napiš na podpora@vpsfree.cz.',
			)
		),
		'mail' => function ($data) {
			$sub = '[vpsFree.cz] Přihláška přijata';
			$body = 'Ahoj '.$data["login"].',

Tvá přihláška byla přijata a bude předložena radě spolku ke schválení. Do 24 hodin Tě budeme kontaktovat.
Pokud by se tak nestalo, obrať se prosím na podpora@vpsfree.cz.

Mezitím doporučujeme, aby sis prošel důkladněji náš web na https://www.vpsfree.cz.
Další informace, které nezbytně potřebuješ vědět, jsou na https://kb.vpsfree.cz/informace/novacci.
A konečně, na naší Knowledge Base je kolekce krátkych návodů, které jsou pro vpsFree specifické, je dobré o nich aspoň mít přehled:
https://kb.vpsfree.cz

Vážíme si Tvého zájmu,

vpsFree.cz
';
			return array($sub, $body);
		}
	),
	'en' => array(
		'form' => array(
			'fields' => array(
				'login' => 'Login name',
				'first_name' => 'First name',
				'surname' => 'Surname',
				'email' => 'E-mail',
				'birth' => 'Year of birth',
				'address' => 'Street, number',
				'zip' => 'ZIP',
				'city' => 'City',
				'country' => 'State',
				'how' => 'How did you find us?',
				'note' => 'Notes',
				'distribution' => 'Choose your distro',
				'location' => 'Preferred location for your VPS',
				'currency' => 'Currency for member fees',
				'org_name' => 'Copmpany name',
				'ic' => 'ID',
			),
			'errors' => array(
				'NOTEMPTY' => 'must be filled',
				'NOTSELECTED' => 'choose one option',
				'LEN_2' => 'must contain at least 2 characters',
				'LEN_6' => 'must contain at least 6 characters',
				'NOTNUM' => 'cannot contain numbers',
				'NUMONLY' => 'can contain only numbers 0-9',
				'NOTLOGIN' => 'can contain only characters a-z, A-Z, 0-9, dash and dot, 2 to 63 chars',
				'NOTMAIL' => 'it is not valid mail address',
				'NOTBIRTH' => 'unacceptable year of birth',
				'NOHOUSEN' => 'ZIP is missing',
				'TWODOTS' => 'cannot contain two dots in a row',
				'TWOHYPHENS' => 'cannot contain two dashes in a row',
				'EEXISTS' => 'this value is already used, choose another',
				'EFAILED' => 'We cannot save your registration. Please try again or contact support: podpora@vpsfree.cz.',
			)
		),
		'mail' => function ($data) {
			$sub = '[vpsFree.cz] Registration accepted';
			$body = 'Hi '.$data["login"].',

Your registration form has been accepted and it will be delivered to board for validation. We will contact you in 24 hours.
If this will not happen please contact us on podpora@vpsfree.cz.

In the meantime we recommend you to read carefully our web https://www.vpsfree.cz.
You can find more info at https://kb.vpsfree.cz/informace/novacci.
Our Knowledge base is collection of very useful information and it is good to know about it:
https://kb.vpsfree.cz

We appreciate your concern,

vpsFree.cz
';
			return array($sub, $body);
		}
	),
);
