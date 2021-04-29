<?php

namespace App\Controllers;

use App\Models\PurchaseHeader;
use App\Models\PurchaseDetail;
use App\Models\PurchasePicture;
 
class PurchasesDeliveryController extends Controller
{
	public function __invoke($request, $response, $params)
	{	
		$company = $_SESSION["company_session"];
		$company->load('suppliers');
		$company->load('productsState');
		
		$project = $_SESSION["project_session"];
		$project->load('purchasesDocumentsTypes');
		
		$args = [
			"navbar" 			=> $this->navbar,
			"suppliers" 		=> $company->suppliers->sortBy("business_name"),
			"documentsTypes" 	=> $project->purchasesDocumentsTypes->where("aff_stock", "<>", 0)->sortBy("description"),
			"productsState" 	=> $company->productsState->sortBy("description"),
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
									->with('purchaseHeader.supplier')
									->get();

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
		$record = $request->getParsedBody();
		
		$detailId = $record["id"];
		PurchaseDetail::find($detailId)->update($record);
				
		return $response->withJson([
			'Result'	=> 'OK',
			'Record'	=> $record,
		]);
	}

	//************//
	//  PICTURES  //
	//************//
	public function picturesActions($request, $response, $args)
	{	
		switch ($args['action'])
		{
			case 'downloadOne': return $this->downloadOnePicture($request, $response, $args);
			case 'downloadAll': return $this->downloadAllPictures($request, $response, $args);
			case 'upload': return $this->uploadPicture($request, $response, $args);
			case 'delete': return $this->deletePicture($request, $response, $args);
			default: return $this->error($request, $response, $args);
		}
	}
	
	private function downloadOnePicture($request, $response, $args)
	{
		$picId	 	= $args["id"];
		$picture 	= PurchasePicture::find($picId);
		
		if ($picture != null)
		{
			return $response->withJson([
				"Result" 	=> "OK",
				"Picture" 	=> $picture,
			]);
		}

		return $response->withJson([
			"Result" 	=> "ERROR",
			"Message" 	=> "Id de foto no encontrado.",
		]);
	}

	private function downloadAllPictures($request, $response, $args)
	{
		$detailId	= $args["id"];
		$detail 	= PurchaseDetail::find($detailId);
		
		if ($detail != null)
		{
			return $response->withJson([
				"Result" 	=> "OK",
				"Pictures" 	=> $detail->pictures(),
			]);
		}

		return $response->withJson([
			"Result" 	=> "ERROR",
			"Message" 	=> "Id de detalle no encontrado.",
		]);
	}
	
	private function uploadPicture($request, $response, $args)
	{
		if (isset($_FILES['picture']) and $_FILES['picture']['error'] == 0 and isset($args["id"]))
		{
			// upload file to server repository
			$project 		= $_SESSION["project_sessionn"];
			
			$publicDir		= '/assets/repository/' . $project->api_key . '/purchase/delivery';
			$privateDir 	= __DIR__ . '/../../public_html' . $publicDir;
			
			$fileKey		= date('YmdHis');
			$fileName		= $fileKey . '_' . $_FILES['picture']['name'];
			$publicFile		= $publicDir . '/' . $fileName;
			$privateFile	= $privateDir . '/' . $fileName;
			
			// create directory if necessary
			if (!file_exists($privateDir)) {
				mkdir($privateDir, 0777, true);
			}
			
			move_uploaded_file($_FILES['picture']['tmp_name'], $privateFile);
			
			// create new picture
			$newPicData = [
				"detail_id"		=> $args["detail_id"],
				"title" 		=> $fileName,
				"public_url"	=> $publicFile,
				"private_url"	=> $privateFile,
			];
			
			PurchasePicture::create($newPicData);
			
			return $response->write("OK");
		}
		else
		{
			return $response->write("ERROR");
		}
	}

	private function deletePicture($request, $response, $args)
	{
		$picId	 	= $args["id"];
		$picture 	= PurchasePicture::find($picId);
		
		if ($picture != null)
		{
			// delete old file
			$this->deletePictureIfExists($picture);

			$picture->delete();

			return $response->withJson([
				"Result" 	=> "OK",
				"Message" 	=> "Foto borrada correctamente",
			]);
		}

		return $response->withJson([
			"Result" 	=> "ERROR",
			"Message" 	=> "Foto no encontrada.",
		]);
	}
	
	private function deletePictureIfExists($pic)
	{
		if (isset($pic->private_url) and $pic->private_url != null)
		{
			// delete old file
			if (file_exists($pic->private_url) and is_file($pic->private_url)) {
				unlink($pic->private_url);
			}
		}
	}
}