<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginHistory extends Model
{
    use HasFactory;
    protected $fillable = ['name','status','ip',
        'user_agent',
        'device',
        'platform',
        'browser',];
}
