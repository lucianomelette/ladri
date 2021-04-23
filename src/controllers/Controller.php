<?php

namespace App\Controllers;

class Controller
{ 
	protected $container;

	protected $navbar;
 
	public function __construct($container)
	{
		$this->container = $container;

		$this->navbar = [
			"username_session" 	=> $_SESSION["user_session"]->username,
			"user_display_name" => $_SESSION["user_session"]->display_name,
			"user_profile"		=> $_SESSION["user_session"]->profile,
			"project_session" 	=> $_SESSION["project_session"]->full_name,
			"company_session" 	=> $_SESSION["company_session"]->business_name,
		];
	}
}