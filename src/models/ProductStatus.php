<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;

class ProductStatus extends Model
{
	protected $table = 'products_state';
	protected $fillable = [
		'id',
		'unique_code',
		'description',
		'company_id',
	];
}