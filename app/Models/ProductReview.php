<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    protected $fillable = [
        'product_id','user_id','rating','title','body',
        'status','is_verified','has_media',
        'moderated_by_id','moderated_at','admin_note',
        'reported_count',
    ];

    protected $casts = [
        'is_verified'   => 'boolean',
        'has_media'     => 'boolean',
        'moderated_at'  => 'datetime',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function user()    { return $this->belongsTo(User::class); }
    public function moderator(){ return $this->belongsTo(User::class, 'moderated_by_id'); }

    public function media()   { return $this->hasMany(ProductReviewMedia::class, 'review_id'); }
    public function votes()   { return $this->hasMany(ProductReviewVote::class, 'review_id'); }
    public function reports() { return $this->hasMany(ProductReviewReport::class, 'review_id'); }
}
