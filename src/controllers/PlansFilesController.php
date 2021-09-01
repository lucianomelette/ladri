<?php

namespace App\Controllers;

use App\Models\PlanFile;
 
class PlansFilesController extends Controller
{
	public function __invoke($request, $response, $params)
	{	
		$project = $_SESSION["project_session"];
		$project->load("plansFiles");

		$plans = $project->plansFiles;

		$args = [
			"navbar"	=> $this->navbar,
			"files"		=> [
				"roger_federer"         => $this->loadFile($plans, "roger_federer"),
				"novak_djokovic"        => $this->loadFile($plans, "novak_djokovic"),
				"rafael_nadal"          => $this->loadFile($plans, "rafael_nadal"),
				"del_potro"             => $this->loadFile($plans, "del_potro"),
				"diego_schwartzman"     => $this->loadFile($plans, "diego_schwartzman"),
				"dominic_thiem"         => $this->loadFile($plans, "dominic_thiem"),
			]
		];
	
		return $this->container->renderer->render($response, 'plans_files.phtml', $args);
	}
	
	private function loadFile($files, $guid)
	{
		if ($files != null)
		{
			$file = $files->where("guid", $guid)->first();
			
			return $this->nvlFile($file);
		}
		
		return $this->defaultFile();
	}
	
	private function defaultFile()
	{
		return (object)[
			'public_url' 		=> '/assets/repository/assets/upload-file-image.png',
			'preview_url' 		=> '/assets/repository/assets/upload-file-image.png',
			'title' 			=> '',
		];
	}
	
	private function nvlFile($file)
	{
		if ($file != null)
		{
			return $file;
		}
		return $this->defaultFile();
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
		$project = $_SESSION["project_session"];
		$project->load("plansFiles");
		
		$planFile = $project->plansFiles->where("guid", $args["guid"])->first();
		
		return $response->withJson([
			"Result" 	=> "OK",
			"File" 		=> $this->nvlFile($planFile),
		]);
	}
	
	private function upload($request, $response, $args)
	{
		$guid 			= $args['guid'];
		$fileToUpload 	= isset($_FILES['plan_file']) ? $_FILES['plan_file'] : null;
		
		if (isset($fileToUpload) and $fileToUpload['error'] == 0 and isset($guid) and $guid != "")
		{
			// upload file to server repository
			$project 		= $_SESSION["project_session"];
			
			$publicDir		= '/assets/repository/companies/' . $project->company->unique_code . '/projects/' . $project->api_key . '/plans';
			$privateDir 	= __DIR__ . '/../../public_html' . $publicDir;
			
			$fileKey 		= $fileToUpload['name'];
			$ext 			= pathinfo($fileKey, PATHINFO_EXTENSION);
			$fileType		= $fileToUpload['type'];
			
			// sanitize string
			if (trim($ext) == "" || trim($ext) != "pdf") {
			    return $response->withJson([
					"Result" 	=> "ERROR",
					"Message"	=> "La extensión del archivo debe ser PDF.",
				]);
			}

			if ($fileType != "application/pdf") {
				return $response->withJson([
					"Result" 	=> "ERROR",
					"Message"	=> "El formato del archivo debe ser PDF.",
				]);
			}
			
			$fileName			= $guid . '_' . $project->api_key . '.' . $ext;
			$publicFile			= $publicDir . '/' . $fileName;
			$privateFile		= $privateDir . '/' . $fileName;
			
			try
			{
				// create directory if necessary
				if (!file_exists($privateDir)) {
					mkdir($privateDir, 0777, true);
				}
				
				// create or update
				$planFile = PlanFile::where("project_id", $project->id)->where("guid", $guid)->first();
				
				if ($planFile == null)
				{
					// create new plan
					$newPlanFileData = [
						"project_id"		=> $project->id,
						"title" 			=> "", //$_FILES['picture']['picture_title'],
						"guid" 				=> $guid,
						"file_name"			=> $fileKey,
						"public_url"		=> $publicFile,
						"private_url"		=> $privateFile,
					];
					
					$planFile = PlanFile::create($newPlanFileData);
				}
				else
				{
					// delete old file
					$this->deleteFileIfExists($planFile->private_url);
					
					// update plan
					$planFile->title 			= ""; //$_FILES['picture']['picture_title'];
					$planFile->file_name		= $fileKey;
					$planFile->public_url		= $publicFile;
					$planFile->private_url		= $privateFile;
					$planFile->save();
				}

				// save file phisically
				move_uploaded_file($fileToUpload['tmp_name'], $privateFile);
				
				return $response->withJson([
					"Result" 	=> "OK",
					"File"		=> $this->nvlFile($planFile),
				]);
			}
			catch (\Exception $e)
			{
				return $response->withJson([
					"Result" 	=> "ERROR",
					"Message"	=> $e->getMessage(),
				]);
			}
		}
		else
		{
			return $response->withJson([
				"Result" 	=> "ERROR",
				"Message"	=> "No se pudo cargar ningún archivo.",
			]);
		}
	}

	private function deleteFileIfExists($private_url)
	{
		if ( isset($private_url) and $private_url != null and file_exists($private_url) and is_file($private_url) )
		{
			unlink($private_url);
		}
	}
	
	public function remove($request, $response, $args)
	{
		$project 	= $_SESSION["project_session"];
		$guid 		= $args['guid'];
		
		// create or update plan
		$planFile = PlanFile::where("project_id", $project->id)->where("guid", $guid)->first();
		
		if ($planFile != null)
		{
		    // delete old file
			$this->deleteFileIfExists($planFile->private_url);
			
			// delete from db
			$planFile->delete();
			
			return $response->withJson([
				"Result" 	=> "OK",
				"File"		=> $this->nvlFile(null)
			]);
		}
		
		return $response->withJson([
			"Result" 	=> "ERROR",
			"Message"	=> "El archivo no existe.",
		]);
	}
	
}