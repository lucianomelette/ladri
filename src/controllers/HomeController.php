<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Company;
use App\Models\Project;
 
class HomeController extends Controller
{
	public function __invoke($request, $response)
	{	
		$args = ["navbar"=> $this->navbar];
	
		return $this->container->renderer->render($response, 'home.phtml', $args);
	}
}