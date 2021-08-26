<?php

namespace App\Controllers;

use App\Models\Exchange as Model;
 
class ExchangeController extends Controller
{
	public function __invoke($request, $response)
	{	
		$args = ["navbar"=> $this->navbar];
	
		return $this->container->renderer->render($response, 'exchange.phtml', $args);
	}

	public function action($request, $response, $args)
	{	
		switch ($args['action'])
		{
			case 'read': return $this->read($request, $response, $args);
			case 'onebydate': return $this->oneByDate($request, $response, $args);
			case 'create': return $this->create($request, $response, $args);
			case 'update': return $this->update($request, $response, $args);
			case 'remove': return $this->remove($request, $response, $args);
			case 'options': return $this->options($request, $response, $args);
			default: return $this->error($request, $response, $args);
		}
	}
	
	private function read($request, $response, $args)
	{
		$company = $_SESSION["company_session"];
		
		$pageSize       = $request->getQueryParam("jtPageSize", $default = null);
		$startIndex     = $request->getQueryParam("jtStartIndex", $default = null);
		$recordsCount   = 0;
		
	    if ($pageSize != null) {
			$records = Model::orderBy('dated_at', 'desc')
							->where('company_id', $company->id)
	                        ->take($pageSize)
	                        ->skip($startIndex)
							->get();
	    }
	    else {
	        $records = Model::orderBy('dated_at', 'desc')
							->where('company_id', $company->id)
							->get();
	    }

		$recordsCount = Model::where('company_id', $company->id)->count();
		
		return $response->withJson([
			"Result" 			=> "OK",
			"Records"			=> $records,
			"TotalRecordCount"	=> $recordsCount,
		]);
	}
	
	private function oneByDate($request, $response, $args)
	{
		$company = $_SESSION["company_session"];

		$datedAt 		= $request->getParsedBody()["dated_at"];
		$currencyCode 	= $request->getParsedBody()["currency_code"];
		
		$exchange =	Model::where('company_id', $company->id)
				->where("currency_code", $currencyCode)
				->where("dated_at", $datedAt)
				->first();
				
		// $exchange =	Model::where("currency_code", $currencyCode)
		// 		->where("dated_at", $datedAt)
		// 		->first();
			
		if ($exchange != null) {
			return $response->withJson([
				"Result" 	=> "OK",
				"Exchange"	=> $exchange,
			]);
		}
		
		return $response->withJson([
			"Result" 	=> "ERROR",
			"Message"	=> "No hay conversión para la moneda " . $currencyCode . " el día " . $datedAt,
		]);
	}
	
	private function create($request, $response, $args)
	{
		$newRecord 					= $request->getParsedBody();
		$newRecord['company_id'] 	= $_SESSION["company_session"]->id;
		
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
	
}