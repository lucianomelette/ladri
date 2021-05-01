<?php

namespace App\Controllers;

use App\Models\PurchaseDetail;
use App\Models\PurchasePicture;
 
class PurchasesDeliveryPicturesController extends Controller
{
	public function __invoke($request, $response, $params)
	{	
		$detail = PurchaseDetail::find($params["detail_id"]);

		if ($detail == null) {
			$args = [
				"navbar" 		=> $this->navbar,
				"error_message" => "El id de talle no existe."
			];

			return $this->container->renderer->render($response, 'purchases_delivery_pictures.phtml', $args);
		}

		$gallery = PurchasePicture::where("detail_id", $params["detail_id"])->get();

		$args = [
			"navbar" 	=> $this->navbar,
			"pictures" 	=> [
				"roger_federer"         => $this->loadPicture($gallery, "roger_federer"),
				"novak_djokovic"        => $this->loadPicture($gallery, "novak_djokovic"),
				"rafael_nadal"          => $this->loadPicture($gallery, "rafael_nadal"),
				"del_potro"             => $this->loadPicture($gallery, "del_potro"),
				"diego_schwartzman"     => $this->loadPicture($gallery, "diego_schwartzman"),
				"dominic_thiem"         => $this->loadPicture($gallery, "dominic_thiem"),
			]
		];
	
		return $this->container->renderer->render($response, 'purchases_delivery_pictures.phtml', $args);
	}
	
	private function loadPicture($gallery, $guid)
	{
		if ($gallery != null)
		{
			$picture = $gallery->where("guid", $guid)->first();
			
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
		
		if (isset($_FILES['picture']) and $_FILES['picture']['error'] == 0)
		{
			// upload file to server repository
			$project 		= $_SESSION["project_session"];
			$detailId 		= $args["detail_id"];
			
			
			$publicDir		= '/assets/repository/' . $club->api_key . '/gallery';
			$privateDir 	= __DIR__ . '/../../public_html' . $publicDir;
			
			$fileKey 		= $_FILES['picture']['name'];
			$ext 			= pathinfo($fileKey, PATHINFO_EXTENSION);
			
			// sanitize string
			if (trim($ext) == "") {
			    $ext = "jpg";
			}
			
			$fileName		= $guid . '.' . $ext;
			$publicFile		= $publicDir . '/' . $fileName;
			$privateFile	= $privateDir . '/' . $fileName;
			
			// create directory if necessary
			if (!file_exists($privateDir)) {
				mkdir($privateDir, 0777, true);
			}
			
			// create or update picture in gallery
			$picture = PurchasePicture::where("guid", $guid)->first();
			
			if ($picture == null)
			{
				// create new picture
				$newPictureData = [
					"detail_id"		=> $detailId,
					"title" 		=> "", //$_FILES['picture']['picture_title'],
					"guid" 			=> $guid,
					"public_url"	=> $publicFile,
					"private_url"	=> $privateFile,
				];
				
				$picture = PurchasePicture::create($newPictureData);
			}
			else
			{
				// delete old file
				$this->deletePhotoIfExists($picture);
				
				// update picture
				$picture->title 		= ""; //$_FILES['picture']['picture_title'];
				$picture->public_url	= $publicFile;
				$picture->private_url	= $privateFile;
				$picture->save();
			}
			
			move_uploaded_file($_FILES['picture']['tmp_name'], $privateFile);
			
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
	
	private function deletePhotoIfExists($picture)
	{
		if ($picture != null and isset($picture->private_url) and $picture->private_url != null)
		{
			// delete old file
			if (file_exists($picture->private_url) and is_file($picture->private_url)) {
				unlink($picture->private_url);
			}
		}
	}
	
	public function remove($request, $response, $args)
	{
		$detailId 	= $args['detail_id'];
		$guid 		= $args['guid'];
		
		// create or update picture in gallery
		$picture = PurchasePicture::where("detail_id", $detailId)->where("guid", $guid)->first();
		
		if ($picture != null)
		{
		    // delete old file
			$this->deletePhotoIfExists($picture);
			
			// delete from db
			$picture->delete();
			
			return $response->withJson([
				"Result" 	=> "OK",
				"Message"	=> "Imagen borrada exitosamente."
			]);
		}
		
		return $response->withJson([
			"Result" 	=> "ERROR",
			"Message"	=> "La imagen no existe.",
		]);
	}
	
}