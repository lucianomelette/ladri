<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;

class PurchasePicture extends Model
{
	protected $table = 'purchases_pictures';
	protected $fillable = [
		'id',
		'detail_id',
		'title',
		'guid',
		'public_url',
		'public_url_thumb',
		'private_url',
		'private_url_thumb',
	];
}