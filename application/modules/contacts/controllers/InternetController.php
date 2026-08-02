<?php

class Contacts_InternetController extends DEEC_Controller_MultiEntityAction
{
	protected function getParentFormClass(): string
	{
		return Contacts_Form_Contact::class;
	}
}
