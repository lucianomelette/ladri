<?php

namespace App\Controllers;

use App\Models\ProductUnit as Model;
 
class ProductsUnitsController extends Controller
{
	public function __invoke($request, $response)
	{	
		$args = ["navbar" => $this->navbar];
	
		return $this->container->renderer->render($response, 'products_units.phtml', $args);
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
		$pageSize       = $request->getQueryParam("jtPageSize", $default = null);
		$startIndex     = $request->getQueryParam("jtStartIndex", $default = null);
		
		$records = Model::where('environment', $_SESSION["company_session"]->environment)
						->when($pageSize != null, function($q) use ($pageSize, $startIndex) {
							$q->take($pageSize)
								->skip($startIndex);
						})
						->orderBy('description')
						->get();
						
		$recordsCount = Model::where('environment', $_SESSION["company_session"]->environment)
							->count();
		
		return $response->withJson([
			"Result" 			=> "OK",
			"Records"			=> $records,
			"TotalRecordCount"	=> $recordsCount,
		]);
	}
	
	private function create($request, $response, $args)
	{
		try
		{
			$newRecord 					= $request->getParsedBody();
			$newRecord['environment'] 	= $_SESSION["company_session"]->environment;
			
			$id = Model::create($newRecord)->id;
			$newRecord['id'] = $id;
			
			return $response->withJson([
				"Result" 	=> "OK",
				"Record"	=> $newRecord,
			]);
		}
		catch (\Exception $e)
		{
			return $response->withJson([
				"Result" 	=> "ERROR",
				"Message"	=> "Código duplicado",
			]);
		}
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
		$options = Model::where('environment', $_SESSION["company_session"]->environment)
							->selectRaw("id as Value, description as DisplayText")
							->orderBy('description', 'asc')
							->get();
		
		return $response->withJson([
			"Result" 	=> "OK",
			"Options"	=> $options,
		]);
	}
}