<?php

namespace App\Controllers;

use App\Models\PurchaseDetail;
use App\Models\PurchasePicture;
 
class PurchasesDeliveryPicturesController extends Controller
{
	public function __invoke($request, $response, $params)
	{	
		$projectId = $_SESSION["project_session"]->id;
		$detail = PurchaseDetail::whereHas('purchaseHeader', function($q) use ($projectId) {
									$q->where("project_id", $projectId);
								})
								->find($params["detail_id"]);

		if ($detail == null) {
			$args = [
				"navbar" 		=> $this->navbar,
				"error_message" => "Id de detalle inexistente."
			];

			return $this->container->renderer->render($response, 'purchases_delivery_pictures.phtml', $args);
		}

		$pics = PurchasePicture::where("detail_id", $params["detail_id"])->get();

		$args = [
			"navbar" 	=> $this->navbar,
			"detailId"	=> $params["detail_id"],
			"pictures" 	=> [
				"roger_federer"         => $this->loadPicture($pics, "roger_federer"),
				"novak_djokovic"        => $this->loadPicture($pics, "novak_djokovic"),
				"rafael_nadal"          => $this->loadPicture($pics, "rafael_nadal"),
				"del_potro"             => $this->loadPicture($pics, "del_potro"),
				"diego_schwartzman"     => $this->loadPicture($pics, "diego_schwartzman"),
				"dominic_thiem"         => $this->loadPicture($pics, "dominic_thiem"),
			]
		];
	
		return $this->container->renderer->render($response, 'purchases_delivery_pictures.phtml', $args);
	}
	
	private function loadPicture($pics, $guid)
	{
		if ($pics != null)
		{
			$picture = $pics->where("guid", $guid)->first();
			
			return $this->nvlPicture($picture);
		}
		
		return $this->defaultPicture();
	}
	
	private function defaultPicture()
	{
		return (object)[
			'public_url' 	=> '/assets/repository/assets/no_photo.jpg',
			'title' 		=> '',
		];
	}
	
	private function nvlPicture($picture)
	{
		if ($picture != null)
			return $picture;
		return $this->defaultPicture();
	}

	public function actions($request, $response, $args)
	{	
		switch ($args['action'])
		{
			case 'download': return $this->download($request, $response, $args);
			case 'upload': return $this->upload($request, $response, $args);
			case 'delete': return $this->remove($request, $response, $args);
			default: return $this->error($request, $response, $args);
		}
	}
	
	private function download($request, $response, $args)
	{
		$picture = PurchasePicture::where("detail_id", $args["detail_id"])
							->where("guid", $args["guid"])
							->first();
		
		return $response->withJson([
			"Result" 	=> "OK",
			"Picture" 	=> $this->nvlPicture($picture),
		]);
	}
	
	private function upload($request, $response, $args)
	{
		$guid = $args['guid'];
		
		if (isset($_FILES['picture']) and $_FILES['picture']['error'] == 0 and isset($guid) and $guid != "")
		{
			// upload file to server repository
			$project 		= $_SESSION["project_session"];
			$detailId 		= $args["detail_id"];
			
			
			$publicDir		= '/assets/repository/' . $project->api_key . '/pictures';
			$privateDir 	= __DIR__ . '/../../public_html' . $publicDir;
			
			$fileKey 		= $_FILES['picture']['name'];
			$ext 			= pathinfo($fileKey, PATHINFO_EXTENSION);
			
			// sanitize string
			if (trim($ext) == "") {
			    $ext = "jpg";
			}
			
			$fileName			= $guid . '_' . $detailId . '.' . $ext;
			$fileNameThumb		= $guid . '_' . $detailId . '._thumb' . $ext;
			$publicFile			= $publicDir . '/' . $fileName;
			$publicFileThumb	= $publicDir . '/' . $fileNameThumb;
			$privateFile		= $privateDir . '/' . $fileName;
			$privateFileThumb	= $privateDir . '/' . $fileNameThumb;
			
			// create directory if necessary
			if (!file_exists($privateDir)) {
				mkdir($privateDir, 0777, true);
			}
			
			// create or update picture
			$picture = PurchasePicture::where("detail_id", $detailId)->where("guid", $guid)->first();
			
			if ($picture == null)
			{
				// create new picture
				$newPictureData = [
					"detail_id"			=> $detailId,
					"title" 			=> "", //$_FILES['picture']['picture_title'],
					"guid" 				=> $guid,
					"public_url"		=> $publicFile,
					"public_thumb_url"	=> $publicFileThumb,
					"private_url"		=> $privateFile,
					"private_thumb_url"	=> $privateFileThumb,
				];
				
				$picture = PurchasePicture::create($newPictureData);
			}
			else
			{
				// delete old file
				$this->deletePhotoIfExists($picture);
				
				// update picture
				$picture->title 			= ""; //$_FILES['picture']['picture_title'];
				$picture->public_url		= $publicFile;
				$picture->public_url_thumb	= $publicFileThumb;
				$picture->private_url		= $privateFile;
				$picture->private_url_thumb	= $privateFileThumb;
				$picture->save();
			}
			
			move_uploaded_file($_FILES['picture']['tmp_name'], $privateFile);
			
			// Compress Image
			$this->compressImage($_FILES['picture']['tmp_name'], $privateFileThumb, 60);
			
			return $response->withJson([
				"Result" 	=> "OK",
				"Picture"	=> $picture,
			]);
		}
		else
		{
			return $response->withJson([
				"Result" 	=> "ERROR",
				"Picture"	=> "No se pudo cargar ninguna imagen.",
			]);
		}
	}

	private function compressImage($source, $destination, $quality) {

		$info = getimagesize($source);
	  
		if ($info['mime'] == 'image/jpeg') 
		  $image = imagecreatefromjpeg($source);
	  
		elseif ($info['mime'] == 'image/gif') 
		  $image = imagecreatefromgif($source);
	  
		elseif ($info['mime'] == 'image/png') 
		  $image = imagecreatefrompng($source);
	  
		imagejpeg($image, $destination, $quality);
	  
	}
	
	private function deletePhotoIfExists($picture)
	{
		if ($picture != null)
		{
			$this->deletePicIfExists($picture->private_url);
			$this->deletePicIfExists($picture->private_url_thumb);
		}
	}

	private function deletePicIfExists($private_url)
	{
		if ( isset($private_url) and $private_url != null and file_exists($private_url) and is_file($private_url) )
		{
			unlink($private_url);
		}
	}
	
	public function remove($request, $response, $args)
	{
		$detailId 	= $args['detail_id'];
		$guid 		= $args['guid'];
		
		// create or update picture
		$picture = PurchasePicture::where("detail_id", $detailId)->where("guid", $guid)->first();
		
		if ($picture != null)
		{
		    // delete old file
			$this->deletePhotoIfExists($picture);
			
			// delete from db
			$picture->delete();
			
			return $response->withJson([
				"Result" 	=> "OK",
				"Picture"	=> $this->nvlPicture(null)
			]);
		}
		
		return $response->withJson([
			"Result" 	=> "ERROR",
			"Message"	=> "La imagen no existe.",
		]);
	}
	
}