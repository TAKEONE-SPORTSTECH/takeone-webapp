<?php

namespace App\Federations\Models;

use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;

/** A file staged by an open event form; attached (moved) when the event saves. */
class FederationUpload extends Model
{
    use DeletesUploadedFiles;

    protected array $fileUploads = ['path' => 'local'];

    protected $fillable = ['token', 'federation_id', 'user_id', 'path', 'mime', 'size', 'filename'];
}
