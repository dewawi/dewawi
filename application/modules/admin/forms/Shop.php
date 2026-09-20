<?php

class Admin_Form_Shop extends DEEC_Form
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
			'name' => 'title',
			'type' => 'text',
			'label' => 'ADMIN_TITLE',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'url',
			'type' => 'text',
			'label' => 'ADMIN_URL',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'activated',
			'type' => 'checkbox',
			'label' => 'ADMIN_ACTIVATED',
			'format' => ['type' => 'int'],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'theme',
			'type' => 'text',
			'label' => 'ADMIN_THEME',
			'format' => ['type' => 'string'],
			'default' => 'default',
			'attribs' => ['maxlength' => 100],
			'tab' => 'design',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'logo',
			'type' => 'text',
			'label' => 'ADMIN_LOGO',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'tab' => 'design',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'contact',
			'type' => 'textarea',
			'label' => 'ADMIN_CONTACT',
			'format' => [
				'type' => 'html',
				'allowTags' => ['a', 'p', 'span', 'img', 'div', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
				'allowAttribs' => ['src', 'style', 'class', 'title', 'href'],
			],
			'attribs' => [
				'rows' => 12,
				'class' => 'editor',
			],
			'tab' => 'content',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'footer',
			'type' => 'textarea',
			'label' => 'ADMIN_FOOTER',
			'format' => [
				'type' => 'html',
				'allowTags' => ['a', 'p', 'span', 'img', 'div', 'br', 'strong', 'em', 'ul', 'ol', 'li'],
				'allowAttribs' => ['src', 'style', 'class', 'title', 'href'],
			],
			'attribs' => [
				'rows' => 8,
				'class' => 'editor',
			],
			'tab' => 'content',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'copyright',
			'type' => 'text',
			'label' => 'ADMIN_COPYRIGHT',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'tab' => 'content',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'catalogenabled',
			'type' => 'checkbox',
			'label' => 'ADMIN_CATALOG_ENABLED',
			'format' => ['type' => 'int'],
			'default' => 0,
			'tab' => 'features',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'checkoutenabled',
			'type' => 'checkbox',
			'label' => 'ADMIN_CHECKOUT_ENABLED',
			'format' => ['type' => 'int'],
			'default' => 0,
			'tab' => 'features',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'contactenabled',
			'type' => 'checkbox',
			'label' => 'ADMIN_CONTACT_ENABLED',
			'format' => ['type' => 'int'],
			'default' => 0,
			'tab' => 'features',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'inquiryenabled',
			'type' => 'checkbox',
			'label' => 'ADMIN_INQUIRY_ENABLED',
			'format' => ['type' => 'int'],
			'default' => 0,
			'tab' => 'features',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'language',
			'type' => 'select',
			'label' => 'ADMIN_LANGUAGE',
			'options' => [],
			'source' => 'language',
			'format' => ['type' => 'string'],
			'tab' => 'settings',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'timezone',
			'type' => 'text',
			'label' => 'ADMIN_TIMEZONE',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'tab' => 'settings',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'emailsender',
			'type' => 'text',
			'label' => 'ADMIN_EMAIL',
			'format' => ['type' => 'string'],
			'attribs' => ['maxlength' => 255],
			'tab' => 'settings',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'analytics',
			'type' => 'textarea',
			'label' => 'ADMIN_ANALYTICS',
			'format' => ['type' => 'string'],
			'attribs' => ['rows' => 12],
			'tab' => 'settings',
			'col' => 12,
		]);
	}
}
