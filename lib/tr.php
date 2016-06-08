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
);
