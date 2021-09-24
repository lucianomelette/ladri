<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;

class PushNotification extends Model
{
	protected $table = 'push_notifications';
	protected $fillable = [
		'id',
		'header_id',
		'module',
		'notification',
		'status',
		'notify_at',
		'ack_by',
		'project_id',
	];
}