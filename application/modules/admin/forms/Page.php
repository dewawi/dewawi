<?php

class Admin_Form_Page extends DEEC_Form
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
			'name' => 'shopid',
			'type' => 'select',
			'label' => 'ADMIN_SHOP',
			'options' => [
				'0' => 'ADMIN_SHOP',
			],
			'source' => 'shop',
			'default' => '0',
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'parentid',
			'type' => 'select',
			'label' => 'ADMIN_PARENT_PAGE',
			'options' => [
				'0' => 'ADMIN_MAIN_PAGE',
			],
			'source' => 'page',
			'default' => '0',
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'title',
			'type' => 'text',
			'label' => 'ADMIN_TITLE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 12,
			],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'type',
			'type' => 'text',
			'label' => 'ADMIN_TYPE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 12,
			],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'slug',
			'type' => 'text',
			'label' => 'ADMIN_SLUG',
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 12,
			],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'image',
			'type' => 'text',
			'label' => 'ADMIN_CATEGORY_IMAGE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'size' => 12,
			],
			'tab' => 'overview',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'content',
			'type' => 'textarea',
			'label' => 'ADMIN_CATEGORY_DESCRIPTION',
			'format' => [
				'type' => 'html',
				'allowTags' => ['a', 'p', 'span', 'img', 'div', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
				'allowAttribs' => ['src', 'style', 'class', 'title', 'href'],
			],
			'attribs' => [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
			'tab' => 'content',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'shortdescription',
			'type' => 'textarea',
			'label' => 'ADMIN_CATEGORY_SHORT_DESCRIPTION',
			'format' => [
				'type' => 'html',
				'allowTags' => ['a', 'p', 'span', 'img', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
				'allowAttribs' => ['src', 'style', 'class', 'title', 'href'],
			],
			'attribs' => [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
			'tab' => 'content',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'minidescription',
			'type' => 'textarea',
			'label' => 'ADMIN_CATEGORY_MINI_DESCRIPTION',
			'format' => [
				'type' => 'html',
				'allowTags' => ['a', 'p', 'span', 'img', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
				'allowAttribs' => ['src', 'style', 'class', 'title', 'href'],
			],
			'attribs' => [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
			'tab' => 'content',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'header',
			'type' => 'textarea',
			'label' => 'ADMIN_HEADER',
			'format' => [
				'type' => 'html',
				'allowTags' => ['a', 'p', 'span', 'img', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
				'allowAttribs' => ['src', 'style', 'class', 'title', 'href'],
			],
			'attribs' => [
				'cols' => 75,
				'rows' => 18,
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
				'allowTags' => ['a', 'p', 'span', 'img', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
				'allowAttribs' => ['src', 'style', 'class', 'title', 'href'],
			],
			'attribs' => [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
			'tab' => 'content',
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'language',
			'type' => 'select',
			'label' => 'ADMIN_LANGUAGE',
			'options' => [],
			'default' => '',
			'tab' => 'settings',
			'col' => 3,
		]);
	}
}
