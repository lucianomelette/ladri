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
		'unit_id',
		'quantity',
		'unit_price',
		'subtotal',
		'tax',
		'total',
		'more_info',
	];
	protected $appends = [
		'has_pictures',
	];

	public function purchaseHeader()
	{
		return $this->hasOne('\App\Models\PurchaseHeader', 'id', 'header_id');
	}

	public function Pictures()
	{
		return $this->hasMany('App\Models\PurchasePicture', 'detail_id', 'id');
	}

	public function getHasPicturesAttribute() {
		return ($this->hasMany('App\Models\PurchasePicture', 'detail_id', 'id')->count() > 0);
	}
}