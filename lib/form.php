<?php
require_once dirname(__FILE__).'/tr.php';

class RegistrationForm {
	private $lang;
	private $valid = true;
	private $errors = array();
	private $data;
	private $entityType;
	private $initial;

	public function __construct($lang, $data = null) {
		$this->lang = $lang;
		$this->initial = array();
		$this->data = $this->clean($data);

		$query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
		$param = trim($query ? $query : '');

		if ($param) {
			list($id, $token) = $this->parseToken($param);
			$this->initial = $this->fetchData($id, $token);

		} elseif ($data && !empty($data['id'])) {
			$this->initial = $this->fetchData($data['id'], $data['token']);
		}
	}

	public function isPost() {
		return $this->data != null;
	}

	public function isResubmit() {
		return count($this->initial) > 0;
	}

	public function isValid($param = null) {
		if ($param === null)
			return $this->valid && count($this->errors) == 0;

		return !array_key_exists($param, $this->errors);
	}

	public function getErrors() {
		return $this->errors;
	}

	public function trError($e) {
		global $TR;

		return $TR[$this->lang]['form']['errors'][$e];
	}

	public function printErrors($response = null) {
		echo '<!--#include virtual="hlavicka.html" -->';
		echo '<form action="./send.php" method="post">';

		if ($response) {
			echo '<div class="alert alert-danger" role="alert">';
			echo $this->trError('REGFAILED').': '.$response->getMessage();
			echo '</div>';

			foreach ($response->getErrors() as $param => $errors) {
				echo '<div class="alert alert-danger" role="alert">';
				echo $this->getLabel($param).': '.implode(', ', $errors);
				echo '</div>';
			}
		}

		foreach ($this->errors as $field => $errors) {
			echo '<div class="alert alert-danger" role="alert">';
			echo $this->getLabel($field).': '.implode('; ', $errors);
			echo '</div>';
		}

		echo '<input type="hidden" name="entity_type" value="'.$this->getEntityType().'">';

		$f = $this;
		include $this->getEntityType().'-osoba/form.php';

		echo '</form>';
		echo '<!--#include virtual="paticka.html" -->';
	}

	public function getLabel($field) {
		global $TR;

		return $TR[$this->lang]['form']['fields'][$field];
	}

	public function getData() {
		return $this->data;
	}

	public function getEntityType() {
		return $this->entityType;
	}

	public function timeZoneOptions() {
		$ret = array();

		foreach (DateTimeZone::listIdentifiers() as $timeZone)
			$ret[$timeZone] = $timeZone;

		return $ret;
	}

	public function isValidationTest() {
		if (!is_array($this->data) || !array_key_exists('_mock', $this->data) || !$this->data['_mock'])
			return false;

		return $this->boolConfig('REGISTRATION_FORM_TEST_MODE', false);
	}

	public function printAntispamFields($honeypotLabel = 'Website') {
		$startedAt = time();
		$token = $this->formToken($startedAt);

		echo '<div style="position: absolute; left: -10000px;" aria-hidden="true">';
		echo '<label for="website">'.htmlspecialchars($honeypotLabel, ENT_QUOTES, 'UTF-8').'</label>';
		echo '<input type="text" id="website" name="website" value="" tabindex="-1" autocomplete="off">';
		echo '</div>';
		echo '<input type="hidden" name="form_started_at" value="'.$startedAt.'">';
		echo '<input type="hidden" name="form_token" value="'.htmlspecialchars($token, ENT_QUOTES, 'UTF-8').'">';
	}

	public function input($name, $type = 'text', $attrs = array()) {
		$ret = '<input
			type="'.$type.'"
			id="'.$name.'"
			name="'.$name.'"
			placeholder="'.$this->getLabel($name).'"
			value="'.htmlspecialchars(isset($_POST[$name]) ? $_POST[$name] : $this->initial[$name]).'"
			class="form-control '.($this->isValid($name) ? '' : 'error').'" ';

		foreach ($attrs as $k => $v)
			$ret .= $k.'="'.$v.'" ';

		$ret .= '>';
		echo $ret;
	}

	public function select($name, $values, $default = null) {
		if (array_key_exists($name, $_POST))
			$selected = $_POST[$name] && array_key_exists($_POST[$name], $values) ? $_POST[$name] : null;

		elseif (array_key_exists($name, $this->initial))
			$selected = $this->initial[$name];

		elseif ($default !== null && array_key_exists($default, $values))
			$selected = $default;

		else
			$selected = null;

		$ret = "<select
			class=\"form-control ".($this->isValid($name) ? '' : 'error')."\"
			id=\"$name\"
			name=\"$name\">\n";
		$ret .= "\t<option disabled ".($selected ? '' : 'selected').">".$this->getLabel($name)."</option>\n";

		foreach ($values as $k => $v)
			$ret .= "\t<option value=\"$k\" ".($selected == $k ? 'selected' : '').">$v</option>\n";

		$ret .= "</select>\n";

		echo $ret;
	}

	public function countrySelect() {
		$values = $this->countryOptions();
		$selected = $this->countryBaseValue($this->postedOrInitialValue('country'));

		$ret = "<select
			class=\"form-control ".($this->isValid('country') ? '' : 'error')."\"
			id=\"country\"
			name=\"country\">\n";
		$ret .= "\t<option disabled ".($selected ? '' : 'selected').">".$this->getLabel('country')."</option>\n";

		foreach ($values as $k => $v)
			$ret .= "\t<option value=\"".htmlspecialchars($k, ENT_QUOTES, 'UTF-8')."\" ".($selected == $k ? 'selected' : '').">".htmlspecialchars($v, ENT_QUOTES, 'UTF-8')."</option>\n";

		$ret .= "</select>\n";

		echo $ret;
	}

	private function postedOrInitialValue($name) {
		if (array_key_exists($name, $_POST))
			return $_POST[$name];

		return array_key_exists($name, $this->initial) ? $this->initial[$name] : '';
	}

	private function countryBaseValue($country) {
		$parts = explode(',', (string)$country, 2);
		return trim($parts[0]);
	}

	private function countryOptions() {
		$top = array('CZ', 'SK', 'DE', 'PL', 'AT', 'GB', 'US');
		$lang = $this->lang === 'cs' ? 'cs' : 'en';
		$labels = $this->countryLabels();
		$localized = array();

		foreach ($labels as $code => $names)
			$localized[$code] = $names[$lang];

		$options = array();

		foreach ($top as $code) {
			$options[$localized[$code]] = $localized[$code];
			unset($localized[$code]);
		}

		asort($localized, SORT_LOCALE_STRING);

		foreach ($localized as $label)
			$options[$label] = $label;

		return $options;
	}

	private function countryLabels() {
		return array(
			'AD' => array('cs' => 'Andorra', 'en' => 'Andorra'),
			'AE' => array('cs' => 'Spojené arabské emiráty', 'en' => 'United Arab Emirates'),
			'AF' => array('cs' => 'Afghánistán', 'en' => 'Afghanistan'),
			'AG' => array('cs' => 'Antigua a Barbuda', 'en' => 'Antigua & Barbuda'),
			'AI' => array('cs' => 'Anguilla', 'en' => 'Anguilla'),
			'AL' => array('cs' => 'Albánie', 'en' => 'Albania'),
			'AM' => array('cs' => 'Arménie', 'en' => 'Armenia'),
			'AO' => array('cs' => 'Angola', 'en' => 'Angola'),
			'AQ' => array('cs' => 'Antarktida', 'en' => 'Antarctica'),
			'AR' => array('cs' => 'Argentina', 'en' => 'Argentina'),
			'AS' => array('cs' => 'Americká Samoa', 'en' => 'American Samoa'),
			'AT' => array('cs' => 'Rakousko', 'en' => 'Austria'),
			'AU' => array('cs' => 'Austrálie', 'en' => 'Australia'),
			'AW' => array('cs' => 'Aruba', 'en' => 'Aruba'),
			'AX' => array('cs' => 'Ålandy', 'en' => 'Åland Islands'),
			'AZ' => array('cs' => 'Ázerbájdžán', 'en' => 'Azerbaijan'),
			'BA' => array('cs' => 'Bosna a Hercegovina', 'en' => 'Bosnia & Herzegovina'),
			'BB' => array('cs' => 'Barbados', 'en' => 'Barbados'),
			'BD' => array('cs' => 'Bangladéš', 'en' => 'Bangladesh'),
			'BE' => array('cs' => 'Belgie', 'en' => 'Belgium'),
			'BF' => array('cs' => 'Burkina Faso', 'en' => 'Burkina Faso'),
			'BG' => array('cs' => 'Bulharsko', 'en' => 'Bulgaria'),
			'BH' => array('cs' => 'Bahrajn', 'en' => 'Bahrain'),
			'BI' => array('cs' => 'Burundi', 'en' => 'Burundi'),
			'BJ' => array('cs' => 'Benin', 'en' => 'Benin'),
			'BL' => array('cs' => 'Svatý Bartoloměj', 'en' => 'St. Barthélemy'),
			'BM' => array('cs' => 'Bermudy', 'en' => 'Bermuda'),
			'BN' => array('cs' => 'Brunej', 'en' => 'Brunei'),
			'BO' => array('cs' => 'Bolívie', 'en' => 'Bolivia'),
			'BQ' => array('cs' => 'Karibské Nizozemsko', 'en' => 'Caribbean Netherlands'),
			'BR' => array('cs' => 'Brazílie', 'en' => 'Brazil'),
			'BS' => array('cs' => 'Bahamy', 'en' => 'Bahamas'),
			'BT' => array('cs' => 'Bhútán', 'en' => 'Bhutan'),
			'BV' => array('cs' => 'Bouvetův ostrov', 'en' => 'Bouvet Island'),
			'BW' => array('cs' => 'Botswana', 'en' => 'Botswana'),
			'BY' => array('cs' => 'Bělorusko', 'en' => 'Belarus'),
			'BZ' => array('cs' => 'Belize', 'en' => 'Belize'),
			'CA' => array('cs' => 'Kanada', 'en' => 'Canada'),
			'CC' => array('cs' => 'Kokosové ostrovy', 'en' => 'Cocos (Keeling) Islands'),
			'CD' => array('cs' => 'Kongo – Kinshasa', 'en' => 'Congo - Kinshasa'),
			'CF' => array('cs' => 'Středoafrická republika', 'en' => 'Central African Republic'),
			'CG' => array('cs' => 'Kongo – Brazzaville', 'en' => 'Congo - Brazzaville'),
			'CH' => array('cs' => 'Švýcarsko', 'en' => 'Switzerland'),
			'CI' => array('cs' => 'Pobřeží slonoviny', 'en' => 'Côte d’Ivoire'),
			'CK' => array('cs' => 'Cookovy ostrovy', 'en' => 'Cook Islands'),
			'CL' => array('cs' => 'Chile', 'en' => 'Chile'),
			'CM' => array('cs' => 'Kamerun', 'en' => 'Cameroon'),
			'CN' => array('cs' => 'Čína', 'en' => 'China'),
			'CO' => array('cs' => 'Kolumbie', 'en' => 'Colombia'),
			'CR' => array('cs' => 'Kostarika', 'en' => 'Costa Rica'),
			'CU' => array('cs' => 'Kuba', 'en' => 'Cuba'),
			'CV' => array('cs' => 'Kapverdy', 'en' => 'Cape Verde'),
			'CW' => array('cs' => 'Curaçao', 'en' => 'Curaçao'),
			'CX' => array('cs' => 'Vánoční ostrov', 'en' => 'Christmas Island'),
			'CY' => array('cs' => 'Kypr', 'en' => 'Cyprus'),
			'CZ' => array('cs' => 'Česko', 'en' => 'Czechia'),
			'DE' => array('cs' => 'Německo', 'en' => 'Germany'),
			'DJ' => array('cs' => 'Džibutsko', 'en' => 'Djibouti'),
			'DK' => array('cs' => 'Dánsko', 'en' => 'Denmark'),
			'DM' => array('cs' => 'Dominika', 'en' => 'Dominica'),
			'DO' => array('cs' => 'Dominikánská republika', 'en' => 'Dominican Republic'),
			'DZ' => array('cs' => 'Alžírsko', 'en' => 'Algeria'),
			'EC' => array('cs' => 'Ekvádor', 'en' => 'Ecuador'),
			'EE' => array('cs' => 'Estonsko', 'en' => 'Estonia'),
			'EG' => array('cs' => 'Egypt', 'en' => 'Egypt'),
			'EH' => array('cs' => 'Západní Sahara', 'en' => 'Western Sahara'),
			'ER' => array('cs' => 'Eritrea', 'en' => 'Eritrea'),
			'ES' => array('cs' => 'Španělsko', 'en' => 'Spain'),
			'ET' => array('cs' => 'Etiopie', 'en' => 'Ethiopia'),
			'FI' => array('cs' => 'Finsko', 'en' => 'Finland'),
			'FJ' => array('cs' => 'Fidži', 'en' => 'Fiji'),
			'FK' => array('cs' => 'Falklandské ostrovy', 'en' => 'Falkland Islands'),
			'FM' => array('cs' => 'Mikronésie', 'en' => 'Micronesia'),
			'FO' => array('cs' => 'Faerské ostrovy', 'en' => 'Faroe Islands'),
			'FR' => array('cs' => 'Francie', 'en' => 'France'),
			'GA' => array('cs' => 'Gabon', 'en' => 'Gabon'),
			'GB' => array('cs' => 'Spojené království', 'en' => 'United Kingdom'),
			'GD' => array('cs' => 'Grenada', 'en' => 'Grenada'),
			'GE' => array('cs' => 'Gruzie', 'en' => 'Georgia'),
			'GF' => array('cs' => 'Francouzská Guyana', 'en' => 'French Guiana'),
			'GG' => array('cs' => 'Guernsey', 'en' => 'Guernsey'),
			'GH' => array('cs' => 'Ghana', 'en' => 'Ghana'),
			'GI' => array('cs' => 'Gibraltar', 'en' => 'Gibraltar'),
			'GL' => array('cs' => 'Grónsko', 'en' => 'Greenland'),
			'GM' => array('cs' => 'Gambie', 'en' => 'Gambia'),
			'GN' => array('cs' => 'Guinea', 'en' => 'Guinea'),
			'GP' => array('cs' => 'Guadeloupe', 'en' => 'Guadeloupe'),
			'GQ' => array('cs' => 'Rovníková Guinea', 'en' => 'Equatorial Guinea'),
			'GR' => array('cs' => 'Řecko', 'en' => 'Greece'),
			'GS' => array('cs' => 'Jižní Georgie a Jižní Sandwichovy ostrovy', 'en' => 'South Georgia & South Sandwich Islands'),
			'GT' => array('cs' => 'Guatemala', 'en' => 'Guatemala'),
			'GU' => array('cs' => 'Guam', 'en' => 'Guam'),
			'GW' => array('cs' => 'Guinea-Bissau', 'en' => 'Guinea-Bissau'),
			'GY' => array('cs' => 'Guyana', 'en' => 'Guyana'),
			'HK' => array('cs' => 'Hongkong – ZAO Číny', 'en' => 'Hong Kong SAR China'),
			'HM' => array('cs' => 'Heardův ostrov a McDonaldovy ostrovy', 'en' => 'Heard & McDonald Islands'),
			'HN' => array('cs' => 'Honduras', 'en' => 'Honduras'),
			'HR' => array('cs' => 'Chorvatsko', 'en' => 'Croatia'),
			'HT' => array('cs' => 'Haiti', 'en' => 'Haiti'),
			'HU' => array('cs' => 'Maďarsko', 'en' => 'Hungary'),
			'ID' => array('cs' => 'Indonésie', 'en' => 'Indonesia'),
			'IE' => array('cs' => 'Irsko', 'en' => 'Ireland'),
			'IL' => array('cs' => 'Izrael', 'en' => 'Israel'),
			'IM' => array('cs' => 'Ostrov Man', 'en' => 'Isle of Man'),
			'IN' => array('cs' => 'Indie', 'en' => 'India'),
			'IO' => array('cs' => 'Britské indickooceánské území', 'en' => 'British Indian Ocean Territory'),
			'IQ' => array('cs' => 'Irák', 'en' => 'Iraq'),
			'IR' => array('cs' => 'Írán', 'en' => 'Iran'),
			'IS' => array('cs' => 'Island', 'en' => 'Iceland'),
			'IT' => array('cs' => 'Itálie', 'en' => 'Italy'),
			'JE' => array('cs' => 'Jersey', 'en' => 'Jersey'),
			'JM' => array('cs' => 'Jamajka', 'en' => 'Jamaica'),
			'JO' => array('cs' => 'Jordánsko', 'en' => 'Jordan'),
			'JP' => array('cs' => 'Japonsko', 'en' => 'Japan'),
			'KE' => array('cs' => 'Keňa', 'en' => 'Kenya'),
			'KG' => array('cs' => 'Kyrgyzstán', 'en' => 'Kyrgyzstan'),
			'KH' => array('cs' => 'Kambodža', 'en' => 'Cambodia'),
			'KI' => array('cs' => 'Kiribati', 'en' => 'Kiribati'),
			'KM' => array('cs' => 'Komory', 'en' => 'Comoros'),
			'KN' => array('cs' => 'Svatý Kryštof a Nevis', 'en' => 'St. Kitts & Nevis'),
			'KP' => array('cs' => 'Severní Korea', 'en' => 'North Korea'),
			'KR' => array('cs' => 'Jižní Korea', 'en' => 'South Korea'),
			'KW' => array('cs' => 'Kuvajt', 'en' => 'Kuwait'),
			'KY' => array('cs' => 'Kajmanské ostrovy', 'en' => 'Cayman Islands'),
			'KZ' => array('cs' => 'Kazachstán', 'en' => 'Kazakhstan'),
			'LA' => array('cs' => 'Laos', 'en' => 'Laos'),
			'LB' => array('cs' => 'Libanon', 'en' => 'Lebanon'),
			'LC' => array('cs' => 'Svatá Lucie', 'en' => 'St. Lucia'),
			'LI' => array('cs' => 'Lichtenštejnsko', 'en' => 'Liechtenstein'),
			'LK' => array('cs' => 'Srí Lanka', 'en' => 'Sri Lanka'),
			'LR' => array('cs' => 'Libérie', 'en' => 'Liberia'),
			'LS' => array('cs' => 'Lesotho', 'en' => 'Lesotho'),
			'LT' => array('cs' => 'Litva', 'en' => 'Lithuania'),
			'LU' => array('cs' => 'Lucembursko', 'en' => 'Luxembourg'),
			'LV' => array('cs' => 'Lotyšsko', 'en' => 'Latvia'),
			'LY' => array('cs' => 'Libye', 'en' => 'Libya'),
			'MA' => array('cs' => 'Maroko', 'en' => 'Morocco'),
			'MC' => array('cs' => 'Monako', 'en' => 'Monaco'),
			'MD' => array('cs' => 'Moldavsko', 'en' => 'Moldova'),
			'ME' => array('cs' => 'Černá Hora', 'en' => 'Montenegro'),
			'MF' => array('cs' => 'Svatý Martin (Francie)', 'en' => 'St. Martin'),
			'MG' => array('cs' => 'Madagaskar', 'en' => 'Madagascar'),
			'MH' => array('cs' => 'Marshallovy ostrovy', 'en' => 'Marshall Islands'),
			'MK' => array('cs' => 'Severní Makedonie', 'en' => 'North Macedonia'),
			'ML' => array('cs' => 'Mali', 'en' => 'Mali'),
			'MM' => array('cs' => 'Myanmar (Barma)', 'en' => 'Myanmar (Burma)'),
			'MN' => array('cs' => 'Mongolsko', 'en' => 'Mongolia'),
			'MO' => array('cs' => 'Macao – ZAO Číny', 'en' => 'Macao SAR China'),
			'MP' => array('cs' => 'Severní Mariany', 'en' => 'Northern Mariana Islands'),
			'MQ' => array('cs' => 'Martinik', 'en' => 'Martinique'),
			'MR' => array('cs' => 'Mauritánie', 'en' => 'Mauritania'),
			'MS' => array('cs' => 'Montserrat', 'en' => 'Montserrat'),
			'MT' => array('cs' => 'Malta', 'en' => 'Malta'),
			'MU' => array('cs' => 'Mauricius', 'en' => 'Mauritius'),
			'MV' => array('cs' => 'Maledivy', 'en' => 'Maldives'),
			'MW' => array('cs' => 'Malawi', 'en' => 'Malawi'),
			'MX' => array('cs' => 'Mexiko', 'en' => 'Mexico'),
			'MY' => array('cs' => 'Malajsie', 'en' => 'Malaysia'),
			'MZ' => array('cs' => 'Mosambik', 'en' => 'Mozambique'),
			'NA' => array('cs' => 'Namibie', 'en' => 'Namibia'),
			'NC' => array('cs' => 'Nová Kaledonie', 'en' => 'New Caledonia'),
			'NE' => array('cs' => 'Niger', 'en' => 'Niger'),
			'NF' => array('cs' => 'Norfolk', 'en' => 'Norfolk Island'),
			'NG' => array('cs' => 'Nigérie', 'en' => 'Nigeria'),
			'NI' => array('cs' => 'Nikaragua', 'en' => 'Nicaragua'),
			'NL' => array('cs' => 'Nizozemsko', 'en' => 'Netherlands'),
			'NO' => array('cs' => 'Norsko', 'en' => 'Norway'),
			'NP' => array('cs' => 'Nepál', 'en' => 'Nepal'),
			'NR' => array('cs' => 'Nauru', 'en' => 'Nauru'),
			'NU' => array('cs' => 'Niue', 'en' => 'Niue'),
			'NZ' => array('cs' => 'Nový Zéland', 'en' => 'New Zealand'),
			'OM' => array('cs' => 'Omán', 'en' => 'Oman'),
			'PA' => array('cs' => 'Panama', 'en' => 'Panama'),
			'PE' => array('cs' => 'Peru', 'en' => 'Peru'),
			'PF' => array('cs' => 'Francouzská Polynésie', 'en' => 'French Polynesia'),
			'PG' => array('cs' => 'Papua-Nová Guinea', 'en' => 'Papua New Guinea'),
			'PH' => array('cs' => 'Filipíny', 'en' => 'Philippines'),
			'PK' => array('cs' => 'Pákistán', 'en' => 'Pakistan'),
			'PL' => array('cs' => 'Polsko', 'en' => 'Poland'),
			'PM' => array('cs' => 'Saint-Pierre a Miquelon', 'en' => 'St. Pierre & Miquelon'),
			'PN' => array('cs' => 'Pitcairnovy ostrovy', 'en' => 'Pitcairn Islands'),
			'PR' => array('cs' => 'Portoriko', 'en' => 'Puerto Rico'),
			'PS' => array('cs' => 'Palestinská území', 'en' => 'Palestinian Territories'),
			'PT' => array('cs' => 'Portugalsko', 'en' => 'Portugal'),
			'PW' => array('cs' => 'Palau', 'en' => 'Palau'),
			'PY' => array('cs' => 'Paraguay', 'en' => 'Paraguay'),
			'QA' => array('cs' => 'Katar', 'en' => 'Qatar'),
			'RE' => array('cs' => 'Réunion', 'en' => 'Réunion'),
			'RO' => array('cs' => 'Rumunsko', 'en' => 'Romania'),
			'RS' => array('cs' => 'Srbsko', 'en' => 'Serbia'),
			'RU' => array('cs' => 'Rusko', 'en' => 'Russia'),
			'RW' => array('cs' => 'Rwanda', 'en' => 'Rwanda'),
			'SA' => array('cs' => 'Saúdská Arábie', 'en' => 'Saudi Arabia'),
			'SB' => array('cs' => 'Šalamounovy ostrovy', 'en' => 'Solomon Islands'),
			'SC' => array('cs' => 'Seychely', 'en' => 'Seychelles'),
			'SD' => array('cs' => 'Súdán', 'en' => 'Sudan'),
			'SE' => array('cs' => 'Švédsko', 'en' => 'Sweden'),
			'SG' => array('cs' => 'Singapur', 'en' => 'Singapore'),
			'SH' => array('cs' => 'Svatá Helena', 'en' => 'St. Helena'),
			'SI' => array('cs' => 'Slovinsko', 'en' => 'Slovenia'),
			'SJ' => array('cs' => 'Špicberky a Jan Mayen', 'en' => 'Svalbard & Jan Mayen'),
			'SK' => array('cs' => 'Slovensko', 'en' => 'Slovakia'),
			'SL' => array('cs' => 'Sierra Leone', 'en' => 'Sierra Leone'),
			'SM' => array('cs' => 'San Marino', 'en' => 'San Marino'),
			'SN' => array('cs' => 'Senegal', 'en' => 'Senegal'),
			'SO' => array('cs' => 'Somálsko', 'en' => 'Somalia'),
			'SR' => array('cs' => 'Surinam', 'en' => 'Suriname'),
			'SS' => array('cs' => 'Jižní Súdán', 'en' => 'South Sudan'),
			'ST' => array('cs' => 'Svatý Tomáš a Princův ostrov', 'en' => 'São Tomé & Príncipe'),
			'SV' => array('cs' => 'Salvador', 'en' => 'El Salvador'),
			'SX' => array('cs' => 'Svatý Martin (Nizozemsko)', 'en' => 'Sint Maarten'),
			'SY' => array('cs' => 'Sýrie', 'en' => 'Syria'),
			'SZ' => array('cs' => 'Eswatini', 'en' => 'Eswatini'),
			'TC' => array('cs' => 'Turks a Caicos', 'en' => 'Turks & Caicos Islands'),
			'TD' => array('cs' => 'Čad', 'en' => 'Chad'),
			'TF' => array('cs' => 'Francouzská jižní území', 'en' => 'French Southern Territories'),
			'TG' => array('cs' => 'Togo', 'en' => 'Togo'),
			'TH' => array('cs' => 'Thajsko', 'en' => 'Thailand'),
			'TJ' => array('cs' => 'Tádžikistán', 'en' => 'Tajikistan'),
			'TK' => array('cs' => 'Tokelau', 'en' => 'Tokelau'),
			'TL' => array('cs' => 'Východní Timor', 'en' => 'Timor-Leste'),
			'TM' => array('cs' => 'Turkmenistán', 'en' => 'Turkmenistan'),
			'TN' => array('cs' => 'Tunisko', 'en' => 'Tunisia'),
			'TO' => array('cs' => 'Tonga', 'en' => 'Tonga'),
			'TR' => array('cs' => 'Turecko', 'en' => 'Türkiye'),
			'TT' => array('cs' => 'Trinidad a Tobago', 'en' => 'Trinidad & Tobago'),
			'TV' => array('cs' => 'Tuvalu', 'en' => 'Tuvalu'),
			'TW' => array('cs' => 'Tchaj-wan', 'en' => 'Taiwan'),
			'TZ' => array('cs' => 'Tanzanie', 'en' => 'Tanzania'),
			'UA' => array('cs' => 'Ukrajina', 'en' => 'Ukraine'),
			'UG' => array('cs' => 'Uganda', 'en' => 'Uganda'),
			'UM' => array('cs' => 'Menší odlehlé ostrovy USA', 'en' => 'U.S. Outlying Islands'),
			'US' => array('cs' => 'Spojené státy', 'en' => 'United States'),
			'UY' => array('cs' => 'Uruguay', 'en' => 'Uruguay'),
			'UZ' => array('cs' => 'Uzbekistán', 'en' => 'Uzbekistan'),
			'VA' => array('cs' => 'Vatikán', 'en' => 'Vatican City'),
			'VC' => array('cs' => 'Svatý Vincenc a Grenadiny', 'en' => 'St. Vincent & Grenadines'),
			'VE' => array('cs' => 'Venezuela', 'en' => 'Venezuela'),
			'VG' => array('cs' => 'Britské Panenské ostrovy', 'en' => 'British Virgin Islands'),
			'VI' => array('cs' => 'Americké Panenské ostrovy', 'en' => 'U.S. Virgin Islands'),
			'VN' => array('cs' => 'Vietnam', 'en' => 'Vietnam'),
			'VU' => array('cs' => 'Vanuatu', 'en' => 'Vanuatu'),
			'WF' => array('cs' => 'Wallis a Futuna', 'en' => 'Wallis & Futuna'),
			'WS' => array('cs' => 'Samoa', 'en' => 'Samoa'),
			'YE' => array('cs' => 'Jemen', 'en' => 'Yemen'),
			'YT' => array('cs' => 'Mayotte', 'en' => 'Mayotte'),
			'ZA' => array('cs' => 'Jihoafrická republika', 'en' => 'South Africa'),
			'ZM' => array('cs' => 'Zambie', 'en' => 'Zambia'),
			'ZW' => array('cs' => 'Zimbabwe', 'en' => 'Zimbabwe'),
		);
	}

	public function clean($data) {
		if (!$data)
			return false;

		$ret = array();

		foreach ($data as $k => $v)
			$ret[$k] = trim($v);

		return $ret;
	}

	public function validate() {
		if (!$this->data)
			return $this->valid = false;

		$entities = array('fyzicka', 'pravnicka');

		if (in_array($this->data['entity_type'], $entities))
			$this->entityType = $this->data['entity_type'];

		else
			$this->entityType = 'fyzicka';

		$fields = array(
			'login',
			'name',
			'email',
			'birth',
			'address',
			'city',
			'zip',
			'country',
			'how',
			'note',
			'distribution',
			'location',
			'currency',
			'time_zone',
		);

		if ($this->data['entity_type'] === 'pravnicka') {
			$fields[] = 'org_name';
			$fields[] = 'ic';
		}

		$v = new Validators($this->lang, $fields);
		$v->validate($this->data);

		foreach ($v->errors as $field => $errors) {
			$this->errors[$field] = array();

			foreach ($errors as $e)
				$this->errors[$field][] = $this->trError($e);
		}

		$this->validateAntispam();

		if ($this->isValid())
			$this->mergeRegionIntoCountry();
	}

	private function mergeRegionIntoCountry() {
		$country = $this->countryBaseValue(isset($this->data['country']) ? $this->data['country'] : '');
		$region = trim(isset($this->data['region']) ? $this->data['region'] : '');

		if ($region !== '')
			$country .= ', '.$region;

		$this->data['country'] = $country;
	}

	protected function validateAntispam() {
		if (!empty($this->data['website'])) {
			$this->errors['form'] = array($this->trError('EFAILED'));
			return;
		}

		$tokenError = $this->validateFormToken();

		if ($tokenError)
			$this->errors['form'] = array($this->trError($tokenError));

		if (!array_key_exists('form', $this->errors) && !$this->skipRateLimit() && !$this->checkRateLimit())
			$this->errors['form'] = array($this->trError('ERATELIMIT'));
	}

	protected function validateFormToken() {
		if (
			empty($this->data['form_started_at']) ||
			!ctype_digit($this->data['form_started_at']) ||
			empty($this->data['form_token'])
		)
			return 'EINVALIDFORM';

		$startedAt = intval($this->data['form_started_at']);
		$age = time() - $startedAt;

		if (!$this->safeEquals($this->formToken($startedAt), $this->data['form_token']))
			return 'EINVALIDFORM';

		if ($age < 5 && !$this->isValidationTest())
			return 'ETOOSOON';

		if ($age > 7200)
			return 'EEXPIRED';

		return null;
	}

	protected function skipRateLimit() {
		return (
			$this->isValidationTest() &&
			!$this->boolConfig('REGISTRATION_RATE_LIMIT_TEST_MODE', false) &&
			empty($this->data['_rate_limit_test'])
		);
	}

	protected function formToken($startedAt) {
		return hash_hmac(
			'sha256',
			'vpsfree-registration-form-v1:'.intval($startedAt),
			$this->antispamSecret()
		);
	}

	protected function checkRateLimit() {
		$ip = $this->remoteIp();

		if (!$ip)
			return true;

		$key = $this->rateLimitKey($ip);
		$path = $this->storagePath('vpsfree-registration-rate-limit.json');
		$now = time();
		$hourAgo = $now - 3600;
		$dayAgo = $now - 86400;
		$limits = array('hour' => 6, 'day' => 20);
		$data = array();

		$fp = @fopen($path, 'c+');

		if (!$fp)
			return $this->rateLimitFailure('Cannot open '.$path);

		if (!@flock($fp, LOCK_EX)) {
			@fclose($fp);
			return $this->rateLimitFailure('Cannot lock '.$path);
		}

		rewind($fp);
		$json = stream_get_contents($fp);
		$decoded = json_decode($json, true);

		if (is_array($decoded))
			$data = $decoded;

		foreach ($data as $k => $attempts) {
			if (!is_array($attempts)) {
				unset($data[$k]);
				continue;
			}

			$data[$k] = array_values(array_filter($attempts, function ($ts) use ($dayAgo) {
				return is_numeric($ts) && $ts >= $dayAgo;
			}));

			if (count($data[$k]) == 0)
				unset($data[$k]);
		}

		$attempts = isset($data[$key]) ? $data[$key] : array();
		$hourAttempts = array_filter($attempts, function ($ts) use ($hourAgo) {
			return $ts >= $hourAgo;
		});

		if (count($hourAttempts) >= $limits['hour'] || count($attempts) >= $limits['day']) {
			@flock($fp, LOCK_UN);
			@fclose($fp);
			return false;
		}

		$attempts[] = $now;
		$data[$key] = $attempts;

		rewind($fp);
		ftruncate($fp, 0);
		$written = fwrite($fp, json_encode($data));
		fflush($fp);
		@chmod($path, 0600);
		@flock($fp, LOCK_UN);
		@fclose($fp);

		if ($written === false)
			return $this->rateLimitFailure('Cannot write '.$path);

		return true;
	}

	protected function remoteIp() {
		$remote = $this->validIp(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null);

		if (!$remote)
			return null;

		if (!$this->isTrustedProxy($remote))
			return $remote;

		$cf = $this->validIp(isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : null);

		if ($cf)
			return $cf;

		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

			foreach ($ips as $ip) {
				$ip = $this->validIp($ip);

				if ($ip)
					return $ip;
			}
		}

		return $remote;
	}

	protected function validIp($ip) {
		$ip = trim((string)$ip);

		if ($ip === '')
			return null;

		return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
	}

	protected function isTrustedProxy($ip) {
		$trusted = $this->trustedProxies();

		foreach (preg_split('/[\s,]+/', $trusted, -1, PREG_SPLIT_NO_EMPTY) as $proxy) {
			if ($proxy === $ip || $this->ipMatchesCidr($ip, $proxy))
				return true;
		}

		return false;
	}

	protected function trustedProxies() {
		$configured = $this->stringConfig('REGISTRATION_TRUSTED_PROXIES');

		if ($configured)
			return $configured;

		return '127.0.0.1 ::1 172.16.0.0/12';
	}

	protected function ipMatchesCidr($ip, $cidr) {
		if (strpos($cidr, '/') === false)
			return false;

		list($subnet, $bits) = explode('/', $cidr, 2);
		$ipBin = @inet_pton($ip);
		$subnetBin = @inet_pton($subnet);

		if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin))
			return false;

		$bits = intval($bits);
		$maxBits = strlen($ipBin) * 8;

		if ($bits < 0 || $bits > $maxBits)
			return false;

		$bytes = (int)floor($bits / 8);
		$remainder = $bits % 8;

		if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes))
			return false;

		if ($remainder === 0)
			return true;

		$mask = (0xff << (8 - $remainder)) & 0xff;

		return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
	}

	protected function rateLimitKey($ip) {
		if (strpos($ip, ':') === false)
			return $ip;

		$packed = @inet_pton($ip);

		if ($packed === false)
			return $ip;

		$hex = bin2hex($packed);

		return substr($hex, 0, 16).'::/64';
	}

	protected function antispamSecret() {
		$configured = $this->stringConfig('REGISTRATION_ANTISPAM_SECRET');

		if ($configured)
			return $configured;

		$path = $this->storagePath('vpsfree-registration-antispam-secret');
		$fp = @fopen($path, 'c+');

		if (!$fp) {
			error_log('registration antispam: cannot open '.$path.', using process-local fallback secret');
			return php_uname('n').':'.__FILE__;
		}

		if (!@flock($fp, LOCK_EX)) {
			@fclose($fp);
			error_log('registration antispam: cannot lock '.$path.', using process-local fallback secret');
			return php_uname('n').':'.__FILE__;
		}

		rewind($fp);
		$secret = trim(stream_get_contents($fp));

		if ($secret === '') {
			$secret = bin2hex($this->secureRandomBytes(32));
			rewind($fp);
			ftruncate($fp, 0);
			fwrite($fp, $secret);
			fflush($fp);
			@chmod($path, 0600);
		}

		@flock($fp, LOCK_UN);
		@fclose($fp);

		return $secret;
	}

	protected function secureRandomBytes($length) {
		if (function_exists('random_bytes'))
			return random_bytes($length);

		if (function_exists('openssl_random_pseudo_bytes')) {
			$strong = false;
			$bytes = openssl_random_pseudo_bytes($length, $strong);

			if ($bytes !== false && $strong)
				return $bytes;
		}

		$ret = '';

		for ($i = 0; $i < $length; $i++)
			$ret .= chr(mt_rand(0, 255));

		error_log('registration antispam: using weak random fallback for token secret');
		return $ret;
	}

	protected function storagePath($file) {
		$dir = $this->stringConfig('REGISTRATION_ANTISPAM_DIR');

		if (!$dir)
			$dir = sys_get_temp_dir();

		if (!is_dir($dir))
			@mkdir($dir, 0700, true);

		return rtrim($dir, '/').'/'.$file;
	}

	protected function stringConfig($name) {
		if (defined($name)) {
			$value = constant($name);

			if ($value !== null && $value !== false && trim((string)$value) !== '')
				return trim((string)$value);
		}

		$value = getenv($name);

		if ($value !== false && trim($value) !== '')
			return trim($value);

		return null;
	}

	protected function boolConfig($name, $default = false) {
		$value = null;

		if (defined($name))
			$value = constant($name);
		else {
			$env = getenv($name);

			if ($env !== false)
				$value = $env;
		}

		if ($value === null)
			return $default;

		return in_array(strtolower(trim((string)$value)), array('1', 'true', 'yes', 'on'), true);
	}

	protected function safeEquals($a, $b) {
		$a = (string)$a;
		$b = (string)$b;

		if (function_exists('hash_equals'))
			return hash_equals($a, $b);

		if (strlen($a) !== strlen($b))
			return false;

		$ret = 0;

		for ($i = 0; $i < strlen($a); $i++)
			$ret |= ord($a[$i]) ^ ord($b[$i]);

		return $ret === 0;
	}

	protected function rateLimitFailure($message) {
		error_log('registration antispam rate limit: '.$message);

		return !$this->boolConfig('REGISTRATION_RATE_LIMIT_FAIL_CLOSED', false);
	}

	protected function parseToken($v) {
		return explode(':', $v);
	}

	protected function fetchData($id, $token) {
		global $api;

		if (!$api)
			$api = new \HaveAPI\Client(API_URL);

		try {
			$r = $api->user_request->registration->preview($id, $token);

		} catch (\HaveAPI\Client\Exception\ActionFailed $e) {
			// TODO: show warning?
			return array();
		}

		list($address, $zip, $city, $country) = $this->parseAddress($r->address);

		return array(
			'id' => $id,
			'token' => $token,
			'login' => $r->login,
			'name' => $r->full_name,
			'org_name' => $r->org_name,
			'ic' => $r->org_id,
			'email' => $r->email,
			'address' => $address,
			'zip' => $zip,
			'city' => $city,
			'country' => $country,
			'birth' => $r->year_of_birth,
			'how' => $r->how,
			'note' => $r->note,
			'distribution' => $r->os_template_id,
			'location' => $r->location_id,
			'currency' => $r->currency,
			'time_zone' => $r->time_zone ?? null,
		);
	}

	protected function parseAddress($address) {
		preg_match(
			'/([^,]+)(,\s*([^\s]+)\s*(([^,]+)(,\s*([^$]+)$)?)?)?/',
			$address,
			$matches
		);

		return array(
			$matches[1],
			$matches[3],
			$matches[5],
			$matches[7],
		);
	}
}

class Validators {
	public $errors = array();
	public $fields;
	private $lang;
	private $db;
	private $mxResolver;

	public function __construct($lang, $fields, $mxResolver = null) {
		$this->lang = $lang;
		$this->fields = $fields;
		$this->mxResolver = $mxResolver;
	}

	public function validate($data) {
		foreach ($this->fields as $f) {
			if (!method_exists($this, $f))
				continue;

			$ret = call_user_func(array($this, $f), $data[$f] ?? null, $data);

			if ($ret === true || (is_array($ret) && count($ret) == 0))
				continue;

			if (is_array($ret))
				$this->errors[$f] = $ret;

			else
				$this->errors[$f] = array($ret);
		}
	}

	public function login($v) {
		$ret = array();

		if (!preg_match('/^[a-zA-Z0-9\-\.]{3,63}$/', $v))
			$ret[] = 'NOTLOGIN';

		if (preg_match('/\.\./', $v))
			$ret[] = 'TWODOTS';

		if (preg_match('/--/', $v))
			$ret[] = 'TWOHYPHENS';

		if (preg_match('/(.)\1{3,}/u', $v))
			$ret[] = 'RANDOMTEXT';

		return $ret;
	}

	public function name($v) {
		$ret = array();

		if (strlen($v) < 5)
			$ret[] = 'LEN_5';

		if (preg_match('/\d/', $v))
			$ret[] = 'NOTNUM';

		if (count($this->words($v)) < 2)
			$ret[] = 'FULLNAME';

		if (preg_match('/(.)\1{3,}/u', $v))
			$ret[] = 'RANDOMTEXT';

		return $ret;
	}

	public function email($v) {
		if (
		 	preg_match('/\s/', $v) ||
			!preg_match('/[^@]+@[a-zA-Z0-9_-]+\.[a-z]+/', $v) ||
			preg_match('/\.\./', $v)
		)
			return 'NOTMAIL';

		// Test whether this mail uses MS mail servers
		$domain = substr($v, strpos($v, '@') + 1);
		$mail_servers = $this->resolveMx($domain);

		if ($mail_servers === false) {
			// No MX record means MS server is not used
			return true;
		}

		foreach ($mail_servers as $mail_server) {
			if (preg_match('/hotmail\.com$/', $mail_server['target']))
				return 'EHOTMAIL';
		}

		return true;
	}

	protected function resolveMx($domain) {
		if ($this->mxResolver)
			return call_user_func($this->mxResolver, $domain);

		return dns_get_record($domain, DNS_MX);
	}

	public function birth($v) {
		$ret = array();
		$y = date('Y');

		if (preg_match('/\D/', $v))
			$ret[] = 'NUMONLY';

		if (count($ret) > 0)
			return $ret;

		if ($v < ($y - 100) || ($y - $v) <= 5)
			$ret[] = 'NOTBIRTH';

		return $ret;
	}

	public function address($v) {
		$ret = array();

		if (strlen($v) < 5)
			$ret[] = 'LEN_5';

		if ($this->looksRandom($v))
			$ret[] = 'RANDOMTEXT';

		# No more validations for English form
		if ($this->lang === 'en')
			return $ret;

		if (!preg_match('/\d/', $v))
			$ret[] = 'NOHOUSEN';

		return $ret;
	}

	public function city($v) {
		$ret = array();

		if (strlen($v) < 2)
			$ret[] = 'LEN_2';

		if (preg_match('/\d/', $v) && !preg_match('/[^\W\d_]/u', $v))
			$ret[] = 'NOTNUM';

		if (preg_match('/(.)\1{3,}/u', $v))
			$ret[] = 'RANDOMTEXT';

		return $ret;
	}

	public function zip($v, $data = array()) {
		$ret = array();
		$v = trim($v);
		$country = $this->normalizeCountryForPostalCode(
			isset($data['country']) ? $data['country'] : ''
		);

		if ($this->postalCodeOptional($country))
			return $this->validateOptionalPostalCode($v);

		if (!$v)
			$ret[] = 'NOTEMPTY';

		if ($v === '')
			return $ret;

		switch ($country) {
		case 'CZ':
		case 'SK':
			if (!preg_match('/^\d{3}\s?\d{2}$/', $v)) {
				if (preg_match('/\D/', preg_replace('/\s/', '', $v)))
					$ret[] = 'NUMONLY';

				$ret[] = 'LEN_5_EXACT';
			}
			break;

		case 'GB':
			if (!preg_match('/^(GIR\s?0AA|[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2})$/i', $v))
				$ret[] = 'POSTALCODE';
			break;

		case 'JP':
			if (!preg_match('/^\d{3}-?\d{4}$/', $v))
				$ret[] = 'POSTALCODE';
			break;

		case 'BR':
			if (!preg_match('/^\d{5}-?\d{3}$/', $v))
				$ret[] = 'POSTALCODE';
			break;

		case 'AU':
			if (!preg_match('/^\d{4}$/', $v))
				$ret[] = 'POSTALCODE';
			break;

		case 'SG':
			if (!preg_match('/^\d{6}$/', $v))
				$ret[] = 'POSTALCODE';
			break;

		default:
			$ret = array_merge($ret, $this->validateGenericPostalCode($v));
			break;
		}

		return $ret;
	}

	private function normalizeCountryForPostalCode($country) {
		$country = trim(explode(',', (string)$country, 2)[0]);

		if ($country === '')
			return null;

		$key = strtolower($this->removeDiacritics($country));
		$key = preg_replace('/[^a-z0-9]+/', ' ', $key);
		$key = trim(preg_replace('/\s+/', ' ', $key));

		$aliases = array(
			'CZ' => array('cz', 'cesko', 'ceska republika', 'czech republic', 'czechia'),
			'SK' => array('sk', 'slovensko', 'slovakia', 'slovak republic'),
			'GB' => array('gb', 'uk', 'united kingdom', 'great britain', 'britain', 'england'),
			'JP' => array('jp', 'japan', 'japonsko'),
			'BR' => array('br', 'brazil', 'brasil', 'brazilie'),
			'AU' => array('au', 'australia', 'australie'),
			'SG' => array('sg', 'singapore', 'singapur'),
			'UG' => array('ug', 'uganda'),
		);

		foreach ($aliases as $code => $names) {
			if (in_array($key, $names, true))
				return $code;
		}

		return null;
	}

	private function postalCodeOptional($country) {
		return in_array($country, array('UG'), true);
	}

	private function validateOptionalPostalCode($v) {
		if ($v === '' || strtolower($v) === 'n/a')
			return array();

		return array('POSTALCODE');
	}

	private function validateGenericPostalCode($v) {
		$ret = array();

		if ($v === '') {
			$ret[] = 'NOTEMPTY';
			return $ret;
		}

		if (strlen($v) < 2 || strlen($v) > 16)
			$ret[] = 'POSTALCODE';

		if (!preg_match('/[a-z0-9]/i', $v))
			$ret[] = 'POSTALCODE';

		if (!preg_match('/^[a-z0-9][a-z0-9 -]*[a-z0-9]$/i', $v))
			$ret[] = 'POSTALCODE';

		if (preg_match('/(.)\1{3,}/', $v))
			$ret[] = 'RANDOMTEXT';

		return array_values(array_unique($ret));
	}

	private function removeDiacritics($v) {
		if (function_exists('iconv')) {
			$ret = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);

			if ($ret !== false)
				return $ret;
		}

		return strtr($v, array(
			'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e',
			'í' => 'i', 'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's',
			'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
			'Á' => 'A', 'Č' => 'C', 'Ď' => 'D', 'É' => 'E', 'Ě' => 'E',
			'Í' => 'I', 'Ň' => 'N', 'Ó' => 'O', 'Ř' => 'R', 'Š' => 'S',
			'Ť' => 'T', 'Ú' => 'U', 'Ů' => 'U', 'Ý' => 'Y', 'Ž' => 'Z',
		));
	}

	public function country($v) {
		$ret = array();

		if (strlen($v) < 2)
			$ret[] = 'LEN_2';

		if (preg_match('/\d/', $v))
			$ret[] = 'NOTNUM';

		if ($this->looksRandom($v))
			$ret[] = 'RANDOMTEXT';

		return $ret;
	}

	public function org_name($v) {
		$ret = array();

		if (strlen($v) < 3)
			$ret[] = 'LEN_3';

		if ($this->looksRandom($v))
			$ret[] = 'RANDOMTEXT';

		return $ret;
	}

	public function ic($v) {
		$v = preg_replace('/\s/', '', $v);

		if (strlen($v) < 6)
			return 'LEN_6';

		if (preg_match('/\D/', $v))
			return 'NUMONLY';

		return true;
	}

	function distribution($v) {
		if (!$v)
			return 'NOTSELECTED';

		return true;
	}

	function location($v) {
		if (!$v)
			return 'NOTSELECTED';

		return true;
	}

	function currency($v) {
		if (!$v)
			return 'NOTSELECTED';

		return true;
	}

	function time_zone($v) {
		if (!$v)
			return true;

		return in_array($v, DateTimeZone::listIdentifiers(), true) ? true : 'NOTSELECTED';
	}

	function how($v) {
		if (!$v)
			return true;

		if ($this->looksRandom($v))
			return 'RANDOMTEXT';

		return true;
	}

	function note($v) {
		if (!$v)
			return true;

		if ($this->looksRandom($v))
			return 'RANDOMTEXT';

		return true;
	}

	private function words($v) {
		$words = preg_split('/[\s,]+/u', trim($v), -1, PREG_SPLIT_NO_EMPTY);

		return array_filter($words, function ($word) {
			return preg_match('/[^\W\d_]{2,}/u', $word);
		});
	}

	private function looksRandom($v) {
		$v = trim($v);

		if ($v === '')
			return false;

		if (preg_match('/(.)\1{3,}/u', $v))
			return true;

		$compact = preg_replace('/[^a-zA-Z0-9]/', '', $v);

		if (strlen($compact) >= 6 && !preg_match('/[aeiouyAEIOUY]/', $compact))
			return true;

		if (strlen($compact) >= 8 && preg_match('/^[a-zA-Z0-9]+$/', $v)) {
			$letters = preg_match_all('/[a-zA-Z]/', $compact);
			$digits = preg_match_all('/\d/', $compact);

			if ($letters > 0 && $digits > 0)
				return true;
		}

		return false;
	}
}
