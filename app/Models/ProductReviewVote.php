<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReviewVote extends Model
{
    protected $fillable = ['review_id','user_id','is_helpful'];
    protected $casts = ['is_helpful' => 'boolean'];

    public function review() { return $this->belongsTo(ProductReview::class, 'review_id'); }
    public function user()   { return $this->belongsTo(User::class); }
}
