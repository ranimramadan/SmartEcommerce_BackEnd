<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReviewMedia extends Model
{
    protected $fillable = ['review_id','file_path','mime_type','size','type','sort_order'];

    public function review() { return $this->belongsTo(ProductReview::class, 'review_id'); }
}
