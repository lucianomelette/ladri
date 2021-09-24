<?php

namespace App\Controllers;

use App\Models\CollectionHeader;
use App\Models\CollectionDetailCash;
use App\Models\CollectionDetailMaterial;
use App\Models\CollectionDetailThirdPartyCheck;
use App\Models\CollectionDetailTransfer;
use App\Models\CollectionDocumentType;
use App\Models\Currency;

use Illuminate\Database\Capsule\Manager as DB;
 
class CollectionsController extends Controller
{
	private $_module = 'collections';

	public function __invoke($request, $response, $params)
	{	
		$company = $_SESSION["company_session"];
		$company->load('customers');
		$company->load('banks');
		$company->load('banksAccounts');
		// $company->load('currencies');
		
		$project = $_SESSION["project_session"];
		$project->load([
			'collectionsDocumentsTypes' => function($q) {
				$q->orderBy("description");
			}
		]);
	
		$args = [
			"navbar"			=> $this->navbar,
			"customers" 		=> $company->customers->sortBy("business_name"),
			"documentsTypes" 	=> $project->collectionsDocumentsTypes,
			"banks" 			=> $company->banks->sortBy("description"),
			"banksAccounts" 	=> $company->banksAccounts,
			"currencies" 		=> Currency::where('disabled', 0)->get(),
		];
		
		if (isset($params["headerId"]) and $params["headerId"] > 0)
		{
			$args["headerId"] = $params["headerId"];
		}
	
		return $this->container->renderer->render($response, 'collections.phtml', $args);
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
		
		$document = CollectionHeader::where('project_id', $_SESSION["project_session"]->id)->find($headerId);
		if ($document != null)
		{
			$document->load("detailsCash");
			$document->load("detailsMaterials");
			$document->load("detailsThirdPartyChecks");
			$document->load("detailsTransfers");
			
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
		
		$records = CollectionHeader::where('project_id', $_SESSION["project_session"]->id)
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
								
		$recordsCount = CollectionHeader::where('project_id', $_SESSION["project_session"]->id)
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
			$headerId = CollectionHeader::create($body)->id;
			
			// save each detail
			$errorOnPaymentType = false;
			$detail = $body["detail"];
			foreach ($detail as $row)
			{
				$row['header_id'] = $headerId;
				
				$type = $row["type"];
				switch($type) {
					case 'cash':
						CollectionDetailCash::create($row);
						break;
						
					case 'materials':
						CollectionDetailMaterial::create($row);
						break;
						
					case 'third-party-check':
						$thirdPartyCheckId = CollectionDetailThirdPartyCheck::create($row)->id;
						$row['id'] = $thirdPartyCheckId;

						// If 'notify_at' is set
						if (isset($row['notify_at']) && !empty($row['notify_at']))
						{
							$this->createNotification($row);
						}
						break;
						
					case 'transfer':
						CollectionDetailTransfer::create($row);
						break;

					default:
						$errorOnPaymentType = true;
				}
			}

			if ($errorOnPaymentType)
			{
				DB::rollBack();
			
				return $response->withJson([
					'status'	=> 'ERROR',
					'message'	=> 'Algún tipo de medio de pago es incorrecto.',
				]);
			}
			
			// update document type sequence
			$docType = CollectionDocumentType::where("unique_code", $body["document_type_code"])->first();
			
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
		catch(\Exception $e)
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
			CollectionHeader::find($headerId)->update($body);
			
			CollectionDetailCash::where("header_id", $headerId)->delete();
			CollectionDetailMaterial::where("header_id", $headerId)->delete();
			CollectionDetailThirdPartyCheck::where("header_id", $headerId)->delete();
			CollectionDetailTransfer::where("header_id", $headerId)->delete();

			$this->deleteNotifications($headerId);
			
			// save each detail
			$errorOnPaymentType = false;
			$detail = $body["detail"];
			foreach ($detail as $row)
			{
				$row['header_id'] = $headerId;
				
				$type = $row["type"];
				switch($type) {
					case 'cash':
						CollectionDetailCash::create($row);
						break;
						
					case 'materials':
						CollectionDetailMaterial::create($row);
						break;
						
					case 'transfer':
						CollectionDetailTransfer::create($row);
						break;
					
					case 'third-party-check':
						$thirdPartyCheckId = CollectionDetailThirdPartyCheck::create($row)->id;
						$row['id'] = $thirdPartyCheckId;
						
						// If 'notify_at' is set
						if (isset($row['notify_at']) && !empty($row['notify_at']))
						{
							$this->createNotification($row);
						}
						break;

					default:
						$errorOnPaymentType = true;
				}
			}

			if ($errorOnPaymentType)
			{
				DB::rollBack();
			
				return $response->withJson([
					'status'	=> 'ERROR',
					'message'	=> 'Algún tipo de medio de pago es incorrecto.',
				]);
			}

			DB::commit();
			
			return $response->withJson([
				'status'	=> 'OK',
				'message'	=> 'Comprobante guardado correctamente',
			]);
		}
		catch(\Exception $e)
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
		DB::beginTransaction();

		try
		{
			$id = $request->getParsedBody()["id"];
			
			CollectionHeader::find($id)
					->update([ "is_canceled" => true ]);

			$this->deleteNotifications($id);

			DB::commit();
			
			return $response->withJson([
				"Result" => "OK",
			]);
		}
		catch(\Exception $e)
		{
			DB::rollBack();
			
			return $response->withJson([
				'status'	=> 'ERROR',
				'message'	=> 'Algo salió mal. Vuelva a intentarlo.',
			]);
		}
	}
	
	public function query($request, $response, $args)
	{
		$company = $_SESSION["company_session"];
		$company->load('customers');
		
		$project = $_SESSION["project_session"];
		$project->load('collectionsDocumentsTypes');
		
		$args = [
			"navbar" 			=> $this->navbar,
			"customers" 		=> $company->customers->sortBy("business_name"),
			"documentsTypes" 	=> $project->collectionsDocumentsTypes->sortBy("description"),
		];
	
		return $this->container->renderer->render($response, 'collections_query.phtml', $args);
	}

	private function createNotification($data)
	{
		$pushNotifController = $this->container['PushNotificationsController'];

		$expAt = date('d/m/Y', strtotime($data['expiration_at']));
		$number = $data['number'];
		$bankName = trim(CollectionDetailThirdPartyCheck::find($data['id'])->bank->description);
		$notification = "El {$expAt} vence el cheque No. {$number} del Banco {$bankName}";

		$newRecord = [
			'module' 		=> $this->_module,
			'header_id'		=> $data['header_id'],
			'notification'	=> $notification,
			'notify_at'		=> $data['notify_at'],
		];

		$pushNotifController->create($newRecord);
	}

	private function deleteNotifications($headerId)
	{
		$pushNotifController = $this->container['PushNotificationsController'];
		$pushNotifController->removeAllByModuleHead($this->_module, $headerId);
	}
}