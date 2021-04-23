<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;

class PurchaseDetail extends Model
{
	protected $table = 'purchases_details';
	protected $fillable = [
		'id',
		'header_id',
		'product_id',
		'product_description',
		'status_id',
		'quantity',
		'unit_price',
		'subtotal',
		'tax',
		'total',
		'more_info',
	];
}