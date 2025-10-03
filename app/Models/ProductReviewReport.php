<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReviewReport extends Model
{
    protected $fillable = ['review_id','user_id','reason','note'];

    public function review() { return $this->belongsTo(ProductReview::class, 'review_id'); }
    public function user()   { return $this->belongsTo(User::class, 'user_id'); }
}
