<?php

class Admin_Form_Config extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'id',
			'type' => 'hidden',
			'format' => ['type' => 'int'],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'timezone',
			'type' => 'text',
			'label' => 'ADMIN_TIMEZONE',
			'section' => 'ADMIN_CONFIG_GENERAL',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'language',
			'type' => 'select',
			'label' => 'ADMIN_LANGUAGE',
			'options' => [],
			'source' => 'language',
			'section' => 'ADMIN_CONFIG_GENERAL',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'logo',
			'type' => 'text',
			'label' => 'ADMIN_LOGO',
			'section' => 'ADMIN_CONFIG_GENERAL',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'footer',
			'type' => 'text',
			'label' => 'ADMIN_FOOTER',
			'section' => 'ADMIN_CONFIG_GENERAL',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'smtphost',
			'type' => 'text',
			'label' => 'ADMIN_SMTP_HOST',
			'section' => 'ADMIN_CONFIG_SMTP',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'smtpport',
			'type' => 'number',
			'label' => 'ADMIN_SMTP_PORT',
			'section' => 'ADMIN_CONFIG_SMTP',
			'format' => ['type' => 'int'],
			'attribs' => [
				'min' => 1,
				'max' => 65535,
			],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'smtpauth',
			'type' => 'checkbox',
			'label' => 'ADMIN_SMTP_AUTH',
			'section' => 'ADMIN_CONFIG_SMTP',
			'format' => ['type' => 'bool'],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'smtpsecure',
			'type' => 'select',
			'label' => 'ADMIN_SMTP_SECURE',
			'options' => [
				'' => 'ADMIN_SELECT',
				'ssl' => 'ADMIN_SMTP_SSL',
				'tls' => 'ADMIN_SMTP_TLS',
			],
			'section' => 'ADMIN_CONFIG_SMTP',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'smtpuser',
			'type' => 'text',
			'label' => 'ADMIN_SMTP_USER',
			'section' => 'ADMIN_CONFIG_SMTP',
			'format' => ['type' => 'string'],
			'attribs' => [
				'autocomplete' => 'username',
			],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'smtppass',
			'type' => 'password',
			'label' => 'ADMIN_SMTP_PASSWORD',
			'description' => 'ADMIN_SMTP_PASSWORD_INFO',
			'section' => 'ADMIN_CONFIG_SMTP',
			'format' => ['type' => 'string'],
			'attribs' => [
				'autocomplete' => 'new-password',
			],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'analytics',
			'type' => 'textarea',
			'label' => 'ADMIN_ANALYTICS',
			'section' => 'ADMIN_CONFIG_TRACKING',
			'format' => ['type' => 'string'],
			'attribs' => [
				'rows' => 12,
			],
			'col' => 12,
		]);
	}
}
