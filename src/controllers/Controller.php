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
			"username_session" 	=> (isset($_SESSION["user_session"]) ? $_SESSION["user_session"]->username : null),
			"user_display_name" => (isset($_SESSION["user_session"]) ? $_SESSION["user_session"]->display_name : null),
			"user_profile"		=> (isset($_SESSION["user_session"]) ? $_SESSION["user_session"]->profile : null),
			"project_session" 	=> (isset($_SESSION["project_session"]) ? $_SESSION["project_session"]->full_name : null),
			"company_session" 	=> (isset($_SESSION["company_session"]) ? $_SESSION["company_session"]->business_name : null),
		];
	}
}