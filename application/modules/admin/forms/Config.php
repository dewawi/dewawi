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
			'tab' => 'general',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'language',
			'type' => 'select',
			'label' => 'ADMIN_LANGUAGE',
			'options' => [],
			'source' => 'language',
			'tab' => 'general',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'logo',
			'type' => 'text',
			'label' => 'ADMIN_LOGO',
			'tab' => 'general',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'footer',
			'type' => 'text',
			'label' => 'ADMIN_FOOTER',
			'tab' => 'general',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'smtphost',
			'type' => 'text',
			'label' => 'ADMIN_SMTP_HOST',
			'tab' => 'smtp',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'smtpport',
			'type' => 'number',
			'label' => 'ADMIN_SMTP_PORT',
			'tab' => 'smtp',
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
			'tab' => 'smtp',
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
			'tab' => 'smtp',
			'format' => ['type' => 'string'],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'smtpuser',
			'type' => 'text',
			'label' => 'ADMIN_SMTP_USER',
			'tab' => 'smtp',
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
			'tab' => 'smtp',
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
			'tab' => 'tracking',
			'format' => ['type' => 'string'],
			'attribs' => [
				'rows' => 12,
			],
			'col' => 12,
		]);
	}
}
