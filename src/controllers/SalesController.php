<?php

namespace App\Controllers;

use App\Models\SaleHeader;
use App\Models\SaleDetail;
use App\Models\SaleDocumentType;

use Illuminate\Database\Capsule\Manager as DB;
 
class SalesController extends Controller
{
	public function __invoke($request, $response, $params)
	{	
		$company = $_SESSION["company_session"];
		$company->load('customers');
		$company->load('products');
		
		$project = $_SESSION["project_session"];
		$project->load('salesDocumentsTypes');
	
		$args = [
			"navbar" 			=> $this->navbar,
			"customers" 		=> $company->customers->sortBy("business_name"),
			"documentsTypes" 	=> $project->salesDocumentsTypes->sortBy("description"),
			"products" 			=> $company->products->sortBy("description"),
		];
		
		if (isset($params["headerId"]) and $params["headerId"] > 0)
		{
			$args["headerId"] = $params["headerId"];
		}
	
		return $this->container->renderer->render($response, 'sales.phtml', $args);
	}
	
	public function action($request, $response, $args)
	{	
		switch ($args['action'])
		{
			case 'one': return $this->one($request, $response, $args);
			case 'read': return $this->read($request, $response, $args);
			case 'create': return $this->create($request, $response, $args);
			case 'update': return $this->update($request, $response, $args);
			case 'remove': return $this->remove($request, $response, $args);
			case 'options': return $this->options($request, $response, $args);
			default: return $this->error($request, $response, $args);
		}
	}
	
	private function one($request, $response, $args)
	{
		$headerId = $args["headerId"];
		
		$document = SaleHeader::where('project_id', $_SESSION["project_session"]->id)->find($headerId);
		if ($document != null)
		{
			$document->load("details");
			
			return $response->withJson([
				"Result" 	=> "OK",
				"Document" 	=> $document,
			]);
		}
		
		return $response->withJson([
			"Result"	=> "ERROR",
			"Message"	=> "No se encuentra el documento.",
		]);
	}
	
	private function read($request, $response, $args)
	{
		$pageSize       = $request->getQueryParam("jtPageSize", $default = null);
		$startIndex     = $request->getQueryParam("jtStartIndex", $default = null);
		$recordsCount   = 0;
		
		$customers_ids		= (isset($request->getParsedBody()["customers_ids"]) ? $request->getParsedBody()["customers_ids"] : null);
		$docs_types_codes	= (isset($request->getParsedBody()["docs_types_codes"]) ? $request->getParsedBody()["docs_types_codes"] : null);
		
		$records = SaleHeader::where('project_id', $_SESSION["project_session"]->id)
								->where('is_canceled', 0)
								->when($customers_ids != null, function($query) use ($customers_ids) {
									$query->whereIn('customer_id', $customers_ids);
								})
								->when($docs_types_codes != null, function($query) use ($docs_types_codes) {
									$query->whereIn('document_type_code', $docs_types_codes);
								})
								->orderBy('dated_at', 'ASC')
								->when($pageSize != null and $startIndex != null, function($query) use ($pageSize, $startIndex) {
									$query->take($pageSize)
										->skip($startIndex);
								})
								->get();
								
		$recordsCount = SaleHeader::where('project_id', $_SESSION["project_session"]->id)
								->where('is_canceled', 0)
								->when($customers_ids != null, function($query) use ($customers_ids) {
									$query->whereIn('customer_id', $customers_ids);
								})
								->when($docs_types_codes != null, function($query) use ($docs_types_codes) {
									$query->whereIn('document_type_code', $docs_types_codes);
								})
								->count();
									
		return $response->withJson([
			"Result" 			=> "OK",
			"Records"			=> $records,
			"TotalRecordCount"	=> $recordsCount,
		]);
	}
	
	private function create($request, $response, $params)
	{
		DB::beginTransaction();

		try
		{
			$body = $request->getParsedBody();
			
			// save header
			$body['project_id'] = $_SESSION["project_session"]->id;		
			$headerId = SaleHeader::create($body)->id;
					
			// save each detail
			$detail = $body["detail"];
			foreach ($detail as $row)
			{
				$row['header_id'] = $headerId;
				SaleDetail::create($row);
			}
			
			// update document type sequence
			$docType = SaleDocumentType::where("unique_code", $body["document_type_code"])->first();
			
			if ($docType != null)
			{
				$docNumber 		= $body["number"];
				$docSequence 	= explode("-", $docNumber)[1]; // take 2nd part of the number
				
				$int_value = ctype_digit($docSequence) ? intval($docSequence) : null;
				if ($int_value !== null)
				{
					$docType->sequence = $int_value + 1;
					$docType->save();
				}
				
				// update customer balance
				if ($docType->balance_multiplier != 0)
				{
					$_SESSION["project_session"]->updateCustomerBalance($body["customer_id"], $docType->balance_multiplier * $body["total"]);
				}
			}

			DB::commit();
			
			return $response->withJson([
				'status'	=> 'OK',
				'message'	=> 'Comprobante guardado correctamente',
			]);
		}
		catch (\Exception $e)
		{
			DB::rollBack();
					
			return $response->withJson([
				'status'	=> 'ERROR',
				'message'	=> 'Algo salió mal. Vuelva a intentarlo.',
			]);
		}
	}
	
	private function update($request, $response, $params)
	{
		DB::beginTransaction();

		try
		{
			$body = $request->getParsedBody();
			
			// save header
			$body['project_id'] = $_SESSION["project_session"]->id;		
			$headerId = $body["id"];
			SaleHeader::find($headerId)->update($body);
					
			// save each detail
			$oldDetail = SaleDetail::where("header_id", $headerId)->get();
			
			$body_r = print_r($body, true);
			$this->container->logger->info("SalesController.update() body: {$body_r}.");
			$this->container->logger->info("SalesController.update() old detail: {$oldDetail}.");
			
			$newDetail = $body["detail"];
			foreach ($newDetail as $row)
			{
				$oldRow = SaleDetail::find($row["detail_id"]);

				// if the detail row doesn't exist... create
				if ($oldRow == null)
				{
					$row['header_id'] = $headerId;
					SaleDetail::create($row);
				}
				// if the detail row already exists... update
				else
				{
					$oldRow->update($row);
				}
			}

			// delete in back, rows deleted in front
			$newDetail_r = print_r($newDetail, true);
			$this->container->logger->info("SalesController.update() detail where to look for: {$newDetail_r}.");
			foreach ($oldDetail as $row)
			{
				$found = false;
				$this->container->logger->info("SalesController.update() searching to delete id: {$row->id}.");
				for ($i = 0; $i < count($newDetail); $i++) {
					if ($row->id == $newDetail[$i]["detail_id"]) {
						$found = true;
					}
					$found_r = $found ? "true" : "false";
					$this->container->logger->info("Old: {$row->id}, new: {$newDetail[$i]["detail_id"]}, found: {$found_r}.");
				}

				// if exists in back, but doesn't in front... delete
				if (!$found) {
					$row->delete();
				}
			}

			DB::commit();
			
			return $response->withJson([
				'status'	=> 'OK',
				'message'	=> 'Comprobante guardado correctamente',
			]);
		}
		catch (\Exception $e)
		{
			DB::rollBack();
					
			return $response->withJson([
				'status'	=> 'ERROR',
				'message'	=> 'Algo salió mal. Vuelva a intentarlo.',
			]);
		}
	}
	
	private function remove($request, $response, $args)
	{
		$id = $request->getParsedBody()["id"];
		
		SaleHeader::find($id)
				->update([ "is_canceled" => true ]);
		
		return $response->withJson([
			"Result" => "OK",
		]);
	}
	
	public function query($request, $response, $args)
	{
		$company = $_SESSION["company_session"];
		$company->load('customers');
		
		$project = $_SESSION["project_session"];
		$project->load('salesDocumentsTypes');
		
		$args = [
			"navbar" 			=> $this->navbar,
			"customers" 		=> $company->customers->sortBy("bussiness_name"),
			"documentsTypes" 	=> $project->salesDocumentsTypes->sortBy("description"),
		];
	
		return $this->container->renderer->render($response, 'sales_query.phtml', $args);
	}
}