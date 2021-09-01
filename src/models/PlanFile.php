<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;

class PlanFile extends Model
{
	protected $table = 'plans_files';
	protected $fillable = [
		'id',
		'title',
		'guid',
		'file_name',
		'public_url',
		'private_url',
		'preview_url',
		'project_id',
	];
}