<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;

class Investor extends Model
{
	protected $table = 'investors';
	protected $fillable = [
		'id',
		'unique_code',
		'fantasy_name',
		'business_name',
		'cuit',
		'category_id',
		'company_id',
	];
	
	protected $appends = [
		'display_name',
		'short_name',
	];
	
	public function getDisplayNameAttribute() {
		return $this->surname . (strlen($this->surname) > 0 ? ", " : "") . $this->full_name;
	}
	
	public function getShortNameAttribute() {
		return $this->surname . (strlen($this->surname) > 0 ? ", " : "") . substr($this->full_name, 0, 1) . ".";
	}
}