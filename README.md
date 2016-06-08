vpsFree.cz
==========

This repository contains the source code of
[https://vpsfree.cz](https://vpsfree.cz) and its translations.

Every translation is in a standalone document root, i.e. `cs/` or `en/` in the
future. The site is built using server-side includes and PHP, so the web server
must support them.

Shared directories, like `css/`, `js/`, `obrazky/` and `download/` have to be
aliased within the document roots.

## Installation

Copy `config.php.dist` to `config.php` and fill in the credentials to the
database. For testing the registration form, you need several database tables:
`members`, `cfg_templates`, `servers` and `locations`.

```sql
CREATE TABLE IF NOT EXISTS `cfg_templates` (
  `templ_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `templ_label` varchar(64) NOT NULL,
  `templ_supported` tinyint(4) NOT NULL DEFAULT '1',
  `templ_order` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`templ_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `locations` (
  `location_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `location_label` varchar(63) NOT NULL,
  PRIMARY KEY (`location_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `members` (
  `m_nick` varchar(63) NOT NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `servers` (
  `server_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `server_name` varchar(64) NOT NULL,
  `server_location` int(10) unsigned NOT NULL,
	`environment_id` int(11) NOT NULL,
  PRIMARY KEY (`server_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
```

This is just to display and validate the registration form, it still cannot be
submitted, as that requires more tables from vpsAdmin.

## Example Apache configuration

```apache
<VirtualHost *:80>
	ServerName vpsfree.cz
	DocumentRoot /var/www/web/cs

	Alias /css /var/www/web/css
	Alias /js /var/www/web/js
	Alias /obrazky /var/www/web/obrazky
	Alias /download /var/www/web/download

	<Directory /var/www/web/cs>
		Options +Includes
		AddOutputFilter INCLUDES .html
		SetEnv no-gzip
	</Directory>
</VirtualHost>

<VirtualHost *:80>
	ServerName vpsfree.org
	DocumentRoot /var/www/web/en

	Alias /css /var/www/web/css
	Alias /js /var/www/web/js
	Alias /obrazky /var/www/web/obrazky
	Alias /download /var/www/web/download

	<Directory /var/www/web/en>
		Options +Includes
		AddOutputFilter INCLUDES .html
		SetEnv no-gzip
	</Directory>
</VirtualHost>
```

## Known issues
In order for [virtual()](https://php.net/virtual) to work, compression must be
disabled, otherwise it results in *Content Encoding Error*.
