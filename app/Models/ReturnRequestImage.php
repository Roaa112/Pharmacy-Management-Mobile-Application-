<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;



class ReturnRequestImage extends Model
{
    protected $fillable = ['return_request_id', 'path'];

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class);
    }
}


