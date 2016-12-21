<?php

class Registration {
	private $lang;
	private $db;
	private $data;

	public function __construct($lang, $db, $data) {
		$this->lang = $lang;
		$this->db = $db;
		$this->data = $data;
		$this->ip = null;
	}

	public function register() {
		if (!$this->save())
			return false;

		$this->mailAdmins();
		$this->mailApplicant();

		return true;
	}

	public function save() {
		$this->created = time();
		$this->name = $this->data["name"];

		if ($this->data['entity_type'] == 'pravnicka')
			$this->name = $this->data["org_name"]." (IČ ".$this->data["ic"]."), ".$this->name;
		
		$ip = $this->getClientIp();

		$ret = $this->db->query("
			INSERT INTO members_changes SET
				m_created = ".$this->created.",
				m_type = 'add',
				m_state = 'awaiting',
				m_nick = '".$this->db->check($this->data["login"])."',
				m_name = '".$this->db->check($this->name)."',
				m_mail = '".$this->db->check($this->data["email"])."',
				m_address = '".$this->db->check($this->data["address"].", ".$this->data["zip"]." ".$this->data["city"].", ".$this->data["country"])."',
				m_year = '".$this->db->check($this->data["birth"])."',
				m_jabber = '',
				m_how = '".$this->db->check($this->data["how"])."',
				m_note = '".$this->db->check($this->data["note"])."',
				m_distribution = '".$this->db->check($this->data["distribution"])."',
				m_location = '".$this->db->check($this->data["location"])."',
				m_currency = '".$this->db->check($this->data["currency"])."',
				m_language = '".$this->lang."',
				m_reason = '',
				m_addr = '".$this->db->check($ip ? $ip : 'undetermined')."',
				m_addr_reverse = '".$this->db->check($ip ? gethostbyaddr($ip) : '')."',
				m_last_mail_id = 1
		");

		if (!$ret)
			return false;

		$this->request_id = $this->db->insert_id();

		return true;
	}

	public function mailAdmins() {
		$admins = explode(",", $this->cfg_get("mailer_requests_sendto"));
		$subject = $this->cfg_get("mailer_requests_admin_sub");
		$text = $this->cfg_get("mailer_requests_admin_text");

		$subject = str_replace("%request_id%", $this->request_id, $subject);
		$subject = str_replace("%type%", "add", $subject);
		$subject = str_replace("%state%", "awaiting", $subject);
		$subject = str_replace("%member_id%", "-", $subject);
		$subject = str_replace("%member%", "-", $subject);
		$subject = str_replace("%name%", $this->name, $subject);

		$text = str_replace("%created%", strftime("%Y-%m-%d %H:%M", $created), $text);
		$text = str_replace("%changed_at%", "-", $text);
		$text = str_replace("%request_id%", $this->request_id, $text);
		$text = str_replace("%type%", "add", $text);
		$text = str_replace("%state%", "awaiting", $text);
		$text = str_replace("%member_id%", "-", $text);
		$text = str_replace("%member%", "-", $text);
		$text = str_replace("%admin_id%", "-", $text);
		$text = str_replace("%admin%", "-", $text);
		$text = str_replace("%reason%", "-", $text);
		$text = str_replace("%admin_response%", "-", $text);
		
		$ip = $this->getClientIp();

		$text = str_replace("%ip%", $ip ? $ip : 'undetermined', $text);
		$text = str_replace("%ptr%", $ip ? gethostbyaddr($ip) : '', $text);

		$distro = $this->db->findByColumnOnce("cfg_templates", "templ_id", $this->data["distribution"]);
		$location = $this->db->findByColumnOnce("locations", "location_id", $this->data["location"]);

		$application = '
        Login: '.$this->data["login"].'
         Name: '.$this->name.'
        Email: '.$this->data["email"].'
       Adresa: '.$this->data["address"].", ".$this->data["zip"]." ".$this->data["city"].", ".$this->data["country"].'
Year of birth: '.$this->data["birth"].'
       Jabber: 
          How: '.$this->data["how"].'
         Note: '.$this->data["note"].'
     Currency: '.$this->data["currency"].'
     Language: '.$this->lang.'
 Distribution: '.$distro["templ_id"].' '.$distro["templ_label"].'
     Location: '.$location["location_id"].' '.$location["location_label"].'
		';

		$text = str_replace("%changed_info%", $application, $text);

		foreach($admins as $admin) {
			$this->sendMail($admin, $subject, $text);
		}
	}

	public function mailApplicant() {
		global $TR;

		$fn = $TR[$this->lang]['mail'];
		list($subject, $text) = $fn($this->data);
	
		$this->sendMail($this->data["email"], $subject, $text);
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

	private function cfg_get($key) {
		$cfg = $this->db->findByColumnOnce("sysconfig", "cfg_name", $key);
		return json_decode($cfg["cfg_value"]);
	}

	private function sendMail($email, $subject, $text) {
		$headers =  "FROM: podpora@vpsfree.cz\n".
					'MIME-Version: 1.0' . "\n" .
					'Content-type: text/plain; charset=UTF-8'."\n".
					'Message-ID: vpsadmin-request-'.$this->request_id.'-1@vpsadmin.vpsfree.cz';

		$preferences = array('input-charset' => 'UTF-8', 'output-charset' => 'UTF-8');
		$encoded_subject = iconv_mime_encode('Subject', $subject, $preferences);
		$encoded_subject = substr($encoded_subject, strlen('Subject: '));
		
		mail($email, $encoded_subject, $text, $headers);
	}
}
