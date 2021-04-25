<?php

namespace App\Controllers;

use App\Models\PurchaseHeader;
use App\Models\PurchaseDetail;
 
class PurchasesDeliveryController extends Controller
{
	public function __invoke($request, $response, $params)
	{	
		$company = $_SESSION["company_session"];
		$company->load('suppliers');
		
		$project = $_SESSION["project_session"];
		$project->load('purchasesDocumentsTypes');
		
		$args = [
			"navbar" 			=> $this->navbar,
			"suppliers" 		=> $company->suppliers->sortBy("business_name"),
			"documentsTypes" 	=> $project->purchasesDocumentsTypes->sortBy("description"),
		];
	
		return $this->container->renderer->render($response, 'purchases_delivery.phtml', $args);
	}
	
	public function action($request, $response, $args)
	{	
		switch ($args['action'])
		{
			case 'read': return $this->read($request, $response, $args);
			case 'update': return $this->update($request, $response, $args);
			default: return $this->error($request, $response, $args);
		}
	}
	
	private function read($request, $response, $args)
	{
		$pageSize       = $request->getQueryParam("jtPageSize", $default = null);
		$startIndex     = $request->getQueryParam("jtStartIndex", $default = null);
		$recordsCount   = 0;
		
		$suppliers_ids		= (isset($request->getParsedBody()["suppliers_ids"]) ? $request->getParsedBody()["suppliers_ids"] : null);
		// $docs_types_codes	= (isset($request->getParsedBody()["docs_types_codes"]) ? $request->getParsedBody()["docs_types_codes"] : null);
		
		$projectId = $_SESSION["project_session"]->id;

		$records = PurchaseDetail::whereHas('purchaseHeader', function($q1) use ($projectId, $suppliers_ids) {
										$q1->whereHas('documentType', function($q2) {
												$q2->whereNotNull('aff_stock')
													->where('aff_stock', '!=', 0);
											})
											->where('project_id', $projectId)
											->where('is_canceled', 0)
											->when($suppliers_ids != null, function($q2) use ($suppliers_ids) {
												$q2->whereIn('supplier_id', $suppliers_ids);
											})
											->orderBy('delivery_date', 'ASC');
									})
									->when($pageSize != null and $startIndex != null, function($query) use ($pageSize, $startIndex) {
										$query->take($pageSize)
											->skip($startIndex);
									})
									->get();

		$records->load('purchaseHeader');

		/*
		$records = PurchaseHeader::where('project_id', $_SESSION["project_session"]->id)
								->where('is_canceled', 0)
								->when($suppliers_ids != null, function($query) use ($suppliers_ids) {
									$query->whereIn('supplier_id', $suppliers_ids);
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
								
		$recordsCount = PurchaseHeader::where('project_id', $_SESSION["project_session"]->id)
								->where('is_canceled', 0)
								->when($suppliers_ids != null, function($query) use ($suppliers_ids) {
									$query->whereIn('supplier_id', $suppliers_ids);
								})
								->when($docs_types_codes != null, function($query) use ($docs_types_codes) {
									$query->whereIn('document_type_code', $docs_types_codes);
								})
								->count();
								*/
									
		return $response->withJson([
			"Result" 			=> "OK",
			"Records"			=> $records,
			"TotalRecordCount"	=> $recordsCount,
		]);
	}
	
	private function update($request, $response, $params)
	{
		$body = $request->getParsedBody();
		
		// save header
		$body['project_id'] = $_SESSION["project_session"]->id;		
		$headerId = $body["id"];
		PurchaseHeader::find($headerId)->update($body);
				
		// save each detail
		PurchaseDetail::where("header_id", $headerId)->delete();
		
		$detail = $body["detail"];
		foreach ($detail as $row)
		{
			$row['header_id'] = $headerId;

			// product status
			if (isset($row['status_id']) && $row['status_id'] == -1)
				unset($row['status_id']);

			PurchaseDetail::create($row);
		}
		
		return $response->withJson([
			'status'	=> 'OK',
			'message'	=> 'Comprobante guardado correctamente',
		]);
	}
}