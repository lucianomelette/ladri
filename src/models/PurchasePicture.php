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
		'private_url',
	];
}