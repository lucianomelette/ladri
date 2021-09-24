<?php

namespace App\Controllers;

use App\Models\PushNotification as Model;
 
class PushNotificationsController extends Controller
{
	public function action($request, $response, $args)
	{	
		switch ($args['action'])
		{
			case 'read': return $this->read($request, $response, $args);
			default: return $this->error($request, $response, $args);
		}
	}
	
	private function read($request, $response, $args)
	{
		$records = Model::where('project_id', $_SESSION["project_session"]->id)
						->where('notify_at', date())
						->get();
		
		return $response->withJson([
			"Result" 			=> "OK",
			"Records"			=> $records,
		]);
	}
	
	public function create($newRecord)
	{
		$newRecord['project_id'] 	= $_SESSION["project_session"]->id;
		$newRecord['status'] 		= 'ACTIVE';

		$id = Model::create($newRecord)->id;
		$newRecord['id'] = $id;
		
		return (object)[
			"Result" 	=> "OK",
			"Record"	=> $newRecord,
		];
	}
	
	// Project, Module & Header ID
	public function removeAllByModuleHead($module, $headerId)
	{
		$projectId = $_SESSION["project_session"]->id;

		Model::where('project_id', $projectId)
				->where('module', $module)
				->where('header_id', $headerId)
				->delete();
		
		return (object)[
			"Result" => "OK"
		];
	}
}