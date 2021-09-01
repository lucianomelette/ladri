<?php

namespace App\Controllers;

class Controller
{ 
	protected $container;

	protected $navbar;
 
	public function __construct($container)
	{
		$this->container = $container;

		$this->buildNavbar();
	}

	protected function buildNavbar()
	{
		$this->navbar = [];

		if (isset($_SESSION["user_session"])) {
			$this->navbar["username_session"] 	= $_SESSION["user_session"]->username;
			$this->navbar["user_display_name"] 	= $_SESSION["user_session"]->display_name;
			$this->navbar["user_profile"] 		= $_SESSION["user_session"]->profile;
		}
		
		if (isset($_SESSION["project_session"])) {
			$this->navbar["project_session"] = $_SESSION["project_session"]->full_name;
		}
		
		if (isset($_SESSION["company_session"])) {
			$this->navbar["company_session"] = $_SESSION["company_session"]->business_name;
		}

		return $this->navbar;
	}
}