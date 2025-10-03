<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductReviewMedia;
use App\Models\ProductReviewVote;
use App\Models\ProductReviewReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductReviewController extends Controller
{
    /* ===================== Helpers ===================== */

    private function authorizeReview(ProductReview $review, Request $request, string $ability): void
    {
        $user = $request->user();

        // صاحب المراجعة مسموح له يحرّر/يحذف مراجعته
        if ($review->user_id === $user->id) return;

        // صلاحيات إدارية
        $map = ['edit' => 'edit_reviews', 'delete' => 'delete_reviews'];
        $needed = $map[$ability] ?? null;

        if ($needed && method_exists($user, 'hasAnyPermission') && $user->hasAnyPermission([$needed])) {
            return;
        }

        abort(403, 'Not allowed.');
    }

    private function hasVerifiedPurchase(int $userId, int $productId): bool
    {
        // فعّلي لاحقًا مع نظام الطلبات
        return false;
    }

    private function containsBadWords(?string $text): bool
    {
        if (!$text) return false;
        $hay = mb_strtolower($text);
        foreach ((array) config('reviews.bad_words', []) as $w) {
            $w = trim($w);
            if ($w !== '' && str_contains($hay, mb_strtolower($w))) return true;
        }
        return false;
    }

    private function recomputeProductAggregates(int $productId): void
    {
        $base = ProductReview::where('product_id',$productId)->where('status','approved');

        $total = (clone $base)->count();
        $sum   = (clone $base)->sum('rating');

        $stars = [];
        for ($i=1;$i<=5;$i++) {
            $stars[$i] = (clone $base)->where('rating',$i)->count();
        }

        $avg = $total > 0 ? round($sum / $total, 2) : 0;

        DB::table('products')->where('id',$productId)->update([
            'reviews_count' => $total,
            'average_rating'=> $avg,
            'star_1_count'  => $stars[1],
            'star_2_count'  => $stars[2],
            'star_3_count'  => $stars[3],
            'star_4_count'  => $stars[4],
            'star_5_count'  => $stars[5],
            'updated_at'    => now(),
        ]);
    }

    /* ===================== Public ===================== */

    // GET /api/products/{product}/reviews?sort=&rating=&with_media=&per_page=
    public function index(Product $product, Request $request)
    {
        $data = $request->validate([
            'q'          => ['nullable','string'],
            'sort'       => ['nullable', Rule::in(['newest','highest','lowest','helpful'])],
            'rating'     => ['nullable','integer','in:1,2,3,4,5'],
            'with_media' => ['nullable','boolean'],
            'per_page'   => ['nullable','integer','min:1','max:50'],
        ]);

        $q = ProductReview::query()
            ->with(['user:id,first_name,last_name','media'])
            ->where('product_id', $product->id)
            ->where('status', 'approved');

        if (!empty($data['q'])) {
            $s = $data['q'];
            $q->where(fn($w)=>$w->where('title','like',"%{$s}%")->orWhere('body','like',"%{$s}%"));
        }
        if (!empty($data['rating']))     $q->where('rating', $data['rating']);
        if (!empty($data['with_media'])) $q->where('has_media', true);

        if (($data['sort'] ?? 'newest') === 'helpful') {
            $q->withCount(['votes as helpful_count' => fn($x)=>$x->where('is_helpful',true)])
              ->orderByDesc('helpful_count')->orderByDesc('created_at');
        } else {
            match($data['sort'] ?? 'newest') {
                'highest' => $q->orderBy('rating','desc')->orderByDesc('created_at'),
                'lowest'  => $q->orderBy('rating','asc')->orderByDesc('created_at'),
                default   => $q->orderByDesc('created_at'),
            };
        }

        return $q->paginate((int)($data['per_page'] ?? 10));
    }

    // GET /api/products/{product}/reviews/summary
    public function summary(Product $product)
    {
        $base = ProductReview::where('product_id',$product->id)->where('status','approved');

        $total = (clone $base)->count();
        $sum   = (clone $base)->sum('rating');

        $distribution = [];
        for ($i=1;$i<=5;$i++) {
            $distribution[$i] = (clone $base)->where('rating',$i)->count();
        }

        $average = $total ? round($sum / $total, 2) : 0;

        return [
            'product_id'   => $product->id,
            'count'        => $total,
            'average'      => $average,
            'distribution' => $distribution,
        ];
    }

    /* ===================== Auth (Users) ===================== */

    // GET /api/products/{product}/reviews/me
    public function myReview(Product $product, Request $request)
    {
        return ProductReview::with('media')
            ->where('product_id',$product->id)
            ->where('user_id',$request->user()->id)
            ->first();
    }

    // POST /api/products/{product}/reviews
    public function store(Product $product, Request $request)
    {
        $data = $request->validate([
            'rating'  => ['required','integer','min:1','max:5'],
            'title'   => ['nullable','string','max:255'],
            'body'    => ['nullable','string'],
            'media'   => ['nullable','array','max:6'],
            'media.*' => ['file','mimetypes:image/jpeg,image/png,image/webp,video/mp4','max:10240'],
        ]);

        return DB::transaction(function () use ($product, $request, $data) {
            $userId = $request->user()->id;

            if (ProductReview::where('product_id',$product->id)->where('user_id',$userId)->exists()) {
                return response()->json(['message'=>'You already reviewed this product'], 422);
            }

            $isVerified = $this->hasVerifiedPurchase($userId, $product->id);

            // سياسة الهجين
            if (config('reviews.auto_approve_verified_only')) {
                $status = $isVerified ? 'approved' : 'pending';
            } else {
                $status = config('reviews.auto_approve', false) ? 'approved' : 'pending';
            }

            // فلتر كلمات بذيئة
            $combined = trim(($data['title'] ?? '').' '.($data['body'] ?? ''));
            if ($this->containsBadWords($combined)) {
                $status = 'pending';
            }

            $review = ProductReview::create([
                'product_id'  => $product->id,
                'user_id'     => $userId,
                'rating'      => $data['rating'],
                'title'       => $data['title'] ?? null,
                'body'        => $data['body'] ?? null,
                'is_verified' => $isVerified,
                'status'      => $status,
                'has_media'   => !empty($data['media']),
            ]);

            if (!empty($data['media'])) {
                foreach ($request->file('media') as $i => $file) {
                    $path = $file->store('review_media','public');
                    ProductReviewMedia::create([
                        'review_id'  => $review->id,
                        'file_path'  => $path,
                        'mime_type'  => $file->getClientMimeType(),
                        'size'       => $file->getSize(),
                        'type'       => str_starts_with($file->getClientMimeType(), 'video/') ? 'video' : 'image',
                        'sort_order' => $i,
                    ]);
                }
            }

            if ($review->status === 'approved') {
                $this->recomputeProductAggregates($product->id);
            }

            return response()->json($review->load('media'), 201);
        });
    }

    // PUT /api/reviews/{review}
    public function update(ProductReview $review, Request $request)
    {
        $this->authorizeReview($review, $request, 'edit');

        $data = $request->validate([
            'rating'        => ['sometimes','integer','min:1','max:5'],
            'title'         => ['nullable','string','max:255'],
            'body'          => ['nullable','string'],
            'new_media'     => ['nullable','array','max:6'],
            'new_media.*'   => ['file','mimetypes:image/jpeg,image/png,image/webp,video/mp4','max:10240'],
            'remove_media'  => ['nullable','array'],
            'remove_media.*'=> ['integer','exists:product_review_media,id'],
        ]);

        return DB::transaction(function () use ($review, $request, $data) {
            $wasApproved = $review->status === 'approved';

            $review->fill(collect($data)->only(['rating','title','body'])->toArray());

            // فلتر بذاءة لو المالك عدّل
            $combined = trim(($review->title ?? '').' '.($review->body ?? ''));
            if ($review->user_id === $request->user()->id && $this->containsBadWords($combined)) {
                $review->status = 'pending';
            }

            $review->save();

            if (!empty($data['remove_media'])) {
                $toRemove = ProductReviewMedia::whereIn('id',$data['remove_media'])
                    ->where('review_id',$review->id)->get();
                foreach ($toRemove as $m) {
                    Storage::disk('public')->delete($m->file_path);
                    $m->delete();
                }
            }

            if (!empty($data['new_media'])) {
                $start = (int) ProductReviewMedia::where('review_id',$review->id)->max('sort_order') + 1;
                foreach ($request->file('new_media') as $i => $file) {
                    $path = $file->store('review_media','public');
                    ProductReviewMedia::create([
                        'review_id'  => $review->id,
                        'file_path'  => $path,
                        'mime_type'  => $file->getClientMimeType(),
                        'size'       => $file->getSize(),
                        'type'       => str_starts_with($file->getClientMimeType(), 'video/') ? 'video' : 'image',
                        'sort_order' => $start + $i,
                    ]);
                }
            }

            $review->has_media = $review->media()->count() > 0;
            $review->save();

            if ($wasApproved !== ($review->status === 'approved')) {
                $this->recomputeProductAggregates($review->product_id);
            }

            return $review->load('media');
        });
    }

    // DELETE /api/reviews/{review}
    public function destroy(ProductReview $review, Request $request)
    {
        $this->authorizeReview($review, $request, 'delete');

        $wasApproved = $review->status === 'approved';
        $productId   = $review->product_id;

        foreach ($review->media as $m) {
            Storage::disk('public')->delete($m->file_path);
        }

        $review->delete();

        if ($wasApproved) {
            $this->recomputeProductAggregates($productId);
        }

        return response()->json(null, 204);
    }

    // POST /api/reviews/{review}/vote  (is_helpful=true|false)
    public function voteHelpful(ProductReview $review, Request $request)
    {
        $data = $request->validate(['is_helpful' => ['required','boolean']]);

        ProductReviewVote::updateOrCreate(
            ['review_id'=>$review->id, 'user_id'=>$request->user()->id],
            ['is_helpful'=>$data['is_helpful']]
        );

        $helpful   = ProductReviewVote::where('review_id',$review->id)->where('is_helpful',true)->count();
        $unhelpful = ProductReviewVote::where('review_id',$review->id)->where('is_helpful',false)->count();

        return ['ok'=>true,'helpful'=>$helpful,'unhelpful'=>$unhelpful];
    }

    // DELETE /api/reviews/{review}/vote
    public function unvoteHelpful(ProductReview $review, Request $request)
    {
        ProductReviewVote::where('review_id',$review->id)->where('user_id',$request->user()->id)->delete();

        $helpful   = ProductReviewVote::where('review_id',$review->id)->where('is_helpful',true)->count();
        $unhelpful = ProductReviewVote::where('review_id',$review->id)->where('is_helpful',false)->count();

        return ['ok'=>true,'helpful'=>$helpful,'unhelpful'=>$unhelpful];
    }

    /* ===================== Moderation & Reports ===================== */

    // PUT /api/reviews/{review}/status  (permission:moderate_reviews)
    public function moderate(Request $request, ProductReview $review)
    {
        $data = $request->validate([
            'status'     => ['required', Rule::in(['approved','pending','rejected'])],
            'admin_note' => ['nullable','string','max:2000'],
        ]);

        $wasApproved = $review->status === 'approved';

        $review->update([
            'status'          => $data['status'],
            'admin_note'      => $data['admin_note'] ?? null,
            'moderated_by_id' => $request->user()->id,
            'moderated_at'    => now(),
        ]);

        if ($wasApproved !== ($review->status === 'approved')) {
            $this->recomputeProductAggregates($review->product_id);
        }

        return $review->fresh();
    }

    // POST /api/reviews/{review}/report
    public function report(ProductReview $review, Request $request)
    {
        $data = $request->validate([
            'reason' => ['required','in:abuse,spam,off_topic,privacy,other'],
            'note'   => ['nullable','string','max:1000'],
        ]);

        ProductReviewReport::updateOrCreate(
            ['review_id'=>$review->id,'user_id'=>$request->user()->id],
            ['reason'=>$data['reason'],'note'=>$data['note'] ?? null]
        );

        $count = $review->reports()->count();
        $wasApproved = $review->status === 'approved';

        $review->reported_count = $count;

        $threshold = (int) config('reviews.report_auto_pending_threshold', 3);
        if ($wasApproved && $count >= $threshold) {
            $review->status = 'pending';
        }
        $review->save();

        if ($wasApproved !== ($review->status === 'approved')) {
            $this->recomputeProductAggregates($review->product_id);
        }

        return ['ok'=>true,'reported_count'=>$count,'status'=>$review->status];
    }

    // GET /api/reviews/reports  (permission:list_reviews)
    public function reportsIndex(Request $request)
    {
        $data = $request->validate([
            'q'             => ['nullable','string'],
            'reason'        => ['nullable', Rule::in(['abuse','spam','off_topic','privacy','other'])],
            'product_id'    => ['nullable','integer'],
            'review_status' => ['nullable', Rule::in(['approved','pending','rejected'])],
            'user_id'       => ['nullable','integer'],
            'per_page'      => ['nullable','integer','min:1','max:50'],
            'sort'          => ['nullable', Rule::in(['newest','oldest'])],
        ]);

        $q = ProductReviewReport::query()
            ->with([
                'user:id,first_name,last_name',
                'review:id,product_id,user_id,status,rating',
                'review.product:id,name',
            ]);

        if (!empty($data['q']))          $q->where('note','like','%'.$data['q'].'%');
        if (!empty($data['reason']))     $q->where('reason',$data['reason']);
        if (!empty($data['user_id']))    $q->where('user_id',$data['user_id']);
        if (!empty($data['product_id'])) $q->whereHas('review', fn($w)=>$w->where('product_id',$data['product_id']));
        if (!empty($data['review_status'])) $q->whereHas('review', fn($w)=>$w->where('status',$data['review_status']));

        match($data['sort'] ?? 'newest') {
            'oldest' => $q->orderBy('created_at','asc'),
            default  => $q->orderBy('created_at','desc'),
        };

        return $q->paginate((int)($data['per_page'] ?? 10));
    }

    // DELETE /api/reviews/reports/{report}  (permission:moderate_reviews)
    public function deleteReport(ProductReviewReport $report)
    {
        $review = $report->review;
        $report->delete();

        $review->reported_count = $review->reports()->count();
        $review->save();

        return ['ok'=>true,'reported_count'=>$review->reported_count];
    }
}
