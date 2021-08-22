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
		'public_url',
		'private_url',
		'project_id',
	];
}