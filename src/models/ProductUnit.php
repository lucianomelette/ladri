<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;

class ProductUnit extends Model
{
	protected $table = 'products_units';
	protected $fillable = [
		'id',
		'unique_code',
		'description',
		'environment',
	];
}