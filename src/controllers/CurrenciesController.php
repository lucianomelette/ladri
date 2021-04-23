<?php

namespace App\Controllers;

use App\Models\Currency as Model;
 
class CurrenciesController extends Controller
{
	public function __invoke($request, $response)
	{	
		$args = ["navbar"=> $this->navbar];
	
		return $this->container->renderer->render($response, 'currencies.phtml', $args);
	}

	public function action($request, $response, $args)
	{	
		switch ($args['action'])
		{
			case 'read': return $this->read($request, $response, $args);
			case 'create': return $this->create($request, $response, $args);
			case 'update': return $this->update($request, $response, $args);
			case 'remove': return $this->remove($request, $response, $args);
			case 'options': return $this->options($request, $response, $args);
			default: return $this->error($request, $response, $args);
		}
	}
	
	private function read($request, $response, $args)
	{
		$records = Model::orderBy('description')
		                ->where('disabled', 0)
	                    ->get();
		
		return $response->withJson([
			"Result" 	=> "OK",
			"Records"	=> $records,
		]);
	}
	
	private function create($request, $response, $args)
	{
		$newRecord 					= $request->getParsedBody();
		//$newRecord['company_id'] 	= $_SESSION["company_session"]->id;
		
		$id = Model::create($newRecord)->id;
		$newRecord['id'] = $id;
		
		return $response->withJson([
			"Result" 	=> "OK",
			"Record"	=> $newRecord,
		]);
	}
	
	private function update($request, $response, $args)
	{
		$updatedRecord = $request->getParsedBody();
		
		Model::find($updatedRecord["id"])
				->update($updatedRecord);
		
		return $response->withJson([
			"Result" 	=> "OK",
			"Record"	=> $updatedRecord,
		]);
	}
	
	private function remove($request, $response, $args)
	{
		$id = $request->getParsedBody()["id"];
		
		Model::find($id)->delete();
		
		return $response->withJson([
			"Result" 	=> "OK",
		]);
	}
	
	private function options($request, $response, $args)
	{
		//$options = Model::where('company_id', $_SESSION["company_session"]->id)
		//					->selectRaw("unique_code as Value, description as DisplayText")
		//					->orderBy('description', 'asc')
		//					->get();
		
		$exchangeEnabled = false;					
	    if (isset($args["exchange"]) && $args["exchange"] == 'EXCHANGE') {
	        $exchangeEnabled = true;
	    }
							
		$options = Model::selectRaw("unique_code as Value, description as DisplayText")
		                    ->where('disabled', 0)
		                    ->where(function($q) use ($exchangeEnabled) {
		                        if ($exchangeEnabled) {
		                            $q->where('exchange_enabled', 1);
		                        }
		                    })
							->orderBy('description', 'asc')
							->get();
		
		return $response->withJson([
			"Result" 	=> "OK",
			"Options"	=> $options,
		]);
	}
}