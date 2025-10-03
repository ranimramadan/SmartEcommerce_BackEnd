<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Controllers: Auth & RBAC
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProfileController;

/*
|--------------------------------------------------------------------------
| Controllers: Catalog
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\AttributeController;
use App\Http\Controllers\Api\AttributeValueController;
use App\Http\Controllers\Api\ProductController;

/*
|--------------------------------------------------------------------------
| Controllers: Reviews / Orders / Payments / Refunds
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\ProductReviewController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentProvidersController;
use App\Http\Controllers\Api\RefundsController;

/*
|--------------------------------------------------------------------------
| Controllers: Cart / Checkout / Invoices / Wishlist
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\InvoicesController;
use App\Http\Controllers\Api\WishlistController;

/*
|--------------------------------------------------------------------------
| Controllers: Shipping (modular)
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\ShipmentsController;
use App\Http\Controllers\Api\ShipmentItemsController;
use App\Http\Controllers\Api\ShippingCarriersController;
use App\Http\Controllers\Api\ShippingRatesController;
use App\Http\Controllers\Api\ShippingZonesController;
use App\Http\Controllers\Api\ShippingQuotesController;
// التتبّع العام برقم فقط (اختياري/توافقي)
use App\Http\Controllers\Api\ShippingController;

/*
|--------------------------------------------------------------------------
| Controllers: Settings / Webhooks / Inventory / Coupons
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SettingsPublicController;
use App\Http\Controllers\Api\WebhooksController;
use App\Http\Controllers\Api\InventoryAdjustmentsController;
use App\Http\Controllers\Api\CouponsController;

/*
|--------------------------------------------------------------------------
| Controllers: BlogPosts / BlogCategories / BlogTag / BlogComments / BlogMedia
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\BlogPostsController;
use App\Http\Controllers\Api\BlogCategoriesController;
use App\Http\Controllers\Api\BlogTagsController;
use App\Http\Controllers\Api\BlogCommentsController;
use App\Http\Controllers\Api\BlogMediaController;


/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/{provider}', [WebhooksController::class, 'handle'])
    ->name('webhooks.handle');

/*
|--------------------------------------------------------------------------
| Public (قراءة فقط)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// Catalog
Route::get('/brands',                 [BrandController::class, 'index']);
Route::get('/brands/{brand}',         [BrandController::class, 'show']);

Route::get('/categories',             [CategoryController::class, 'index']);
Route::get('/categories/{category}',  [CategoryController::class, 'show']);
Route::get('/categories-tree',        [CategoryController::class, 'tree']);

Route::get('/attributes',                             [AttributeController::class, 'index']);
Route::get('/attributes/{attribute}',                 [AttributeController::class, 'show']);
Route::get('/attributes/{attribute}/values',          [AttributeValueController::class, 'index']);
Route::get('/values/{value}',                         [AttributeValueController::class, 'show']);

Route::get('/products',               [ProductController::class, 'index']);
Route::get('/products/{product}',     [ProductController::class, 'show']);

// Reviews (قراءة عامة)
Route::get('/products/{product}/reviews',         [ProductReviewController::class, 'index']);
Route::get('/products/{product}/reviews/summary', [ProductReviewController::class, 'summary']);

// طرق الدفع المتاحة عامة (مثلاً للـ checkout قبل تسجيل الدخول إن لزم)
Route::get('/payment-methods', [PaymentController::class, 'availableMethods']);

// Cart (عرض فقط — يدعم الضيف عبر X-Session-Id)
Route::get('/cart', [CartController::class, 'show']);

// Shipping: عرض عروض الشحن + تتبّع عام برقم
Route::post('/shipping/quote', [ShippingQuotesController::class, 'quote']);
Route::post('/shipping/track', [ShippingController::class, 'trackByNumber']);

// Settings العامة للواجهة
Route::get('/public/settings', [SettingsPublicController::class, 'index']);

// Blog
Route::prefix('blog/public')->group(function () {
    // Posts
    Route::get('/posts',               [BlogPostsController::class, 'publicIndex']);
    Route::get('/posts/{post}',        [BlogPostsController::class, 'publicShow']); // id أو slug

    // Categories
    Route::get('/categories',          [BlogCategoriesController::class, 'publicIndex']);
    Route::get('/categories/{cat}',    [BlogCategoriesController::class, 'publicShow']); // id أو slug

    // Tags
    Route::get('/tags',                [BlogTagsController::class, 'publicIndex']);
    Route::get('/tags/{tag}',          [BlogTagsController::class, 'publicShow']); // id أو slug

    // Comments (على بوست منشور فقط)
    Route::get('/posts/{post}/comments', [BlogCommentsController::class, 'publicIndex']);
    Route::post('/posts/{post}/comments', [BlogCommentsController::class, 'publicStore']);

    // Media (معرض صور بوست منشور)
    Route::get('/posts/{post}/media',  [BlogMediaController::class, 'publicIndex']);
});

/*
|--------------------------------------------------------------------------
| Wishlist (عامّة + Stateful للضيف عبر X-Session-Id)
|--------------------------------------------------------------------------
| - لا تتطلب auth. الضيف يتعرّف بـ X-Session-Id (هيدر/كوكي).
| - لو المستخدم مسجّل، الكنترولر يربط/يدمج تلقائيًا.
*/
Route::prefix('wishlist')->group(function () {
    Route::get('/',     [WishlistController::class, 'index']);
    Route::post('/add', [WishlistController::class, 'add']);
    Route::delete('/remove', [WishlistController::class, 'remove']);
    Route::post('/clear',    [WishlistController::class, 'clear']);
});

/*
|--------------------------------------------------------------------------
| Protected (auth:sanctum + active)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active'])->group(function () {

    // Auth & Profile
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
    });

    // ===============================
    // Users / Roles / Permissions
    // ===============================
    Route::prefix('users')->group(function () {
        Route::get('/',        [UserController::class, 'index'])->middleware('permission:view_users');
        Route::post('/',       [UserController::class, 'store'])->middleware('permission:create_users');
        Route::get('/{user}',  [UserController::class, 'show'])->middleware('permission:view_users');
        Route::put('/{user}',  [UserController::class, 'update'])->middleware('permission:edit_users');
        Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('permission:delete_users');
        Route::post('/{user}/roles', [UserController::class, 'assignRole'])->middleware('permission:assign_roles');
    });

    Route::prefix('roles')->group(function () {
        Route::get('/',            [RoleController::class, 'index'])->middleware('permission:view_roles');
        Route::post('/',           [RoleController::class, 'store'])->middleware('permission:create_roles');
        Route::get('/{role}',      [RoleController::class, 'show'])->middleware('permission:view_roles');
        Route::put('/{role}',      [RoleController::class, 'update'])->middleware('permission:edit_roles');
        Route::delete('/{role}',   [RoleController::class, 'destroy'])->middleware('permission:delete_roles');
        Route::post('/{role}/permissions', [RoleController::class, 'assignPermissions'])->middleware('permission:assign_permissions');
    });

    Route::prefix('permissions')->group(function () {
        Route::get('/',                 [PermissionController::class, 'index'])->middleware('permission:view_permissions');
        Route::post('/',                [PermissionController::class, 'store'])->middleware('permission:create_permissions');
        Route::get('/{permission}',     [PermissionController::class, 'show'])->middleware('permission:view_permissions');
        Route::put('/{permission}',     [PermissionController::class, 'update'])->middleware('permission:edit_permissions');
        Route::delete('/{permission}',  [PermissionController::class, 'destroy'])->middleware('permission:delete_permissions');
    });

    // ===============================
    // Catalog Management
    // ===============================
    Route::middleware('permission:manage_catalog')->group(function () {
        // Brands
        Route::post('/brands',               [BrandController::class, 'store'])->middleware('permission:create_brands');
        Route::put('/brands/{brand}',        [BrandController::class, 'update'])->middleware('permission:edit_brands');
        Route::delete('/brands/{brand}',     [BrandController::class, 'destroy'])->middleware('permission:delete_brands');

        // Categories
        Route::post('/categories',                 [CategoryController::class, 'store'])->middleware('permission:create_categories');
        Route::put('/categories/{category}',       [CategoryController::class, 'update'])->middleware('permission:edit_categories');
        Route::delete('/categories/{category}',    [CategoryController::class, 'destroy'])->middleware('permission:delete_categories');
        Route::post('/categories/{category}/attributes/sync', [CategoryController::class, 'syncAttributes'])->middleware('permission:manage_category_attributes');

        // Attributes & Values
        Route::post('/attributes',                 [AttributeController::class, 'store'])->middleware('permission:create_attributes');
        Route::put('/attributes/{attribute}',      [AttributeController::class, 'update'])->middleware('permission:edit_attributes');
        Route::delete('/attributes/{attribute}',   [AttributeController::class, 'destroy'])->middleware('permission:delete_attributes');

        Route::post('/attributes/{attribute}/values', [AttributeValueController::class, 'store'])->middleware('permission:create_attribute_values');
        Route::put('/values/{value}',                 [AttributeValueController::class, 'update'])->middleware('permission:edit_attribute_values');
        Route::delete('/values/{value}',              [AttributeValueController::class, 'destroy'])->middleware('permission:delete_attribute_values');
    });

    // Products
    Route::prefix('products')->group(function () {
        Route::post('/',                    [ProductController::class, 'store'])->middleware('permission:create_products');
        Route::put('/{product}',            [ProductController::class, 'update'])->middleware('permission:edit_products');
        Route::delete('/{product}',         [ProductController::class, 'destroy'])->middleware('permission:delete_products');
        Route::post('/{product}/images',    [ProductController::class, 'addImages'])->middleware('permission:manage_product_images');
        Route::delete('/{product}/images/{image}', [ProductController::class, 'removeImage'])->middleware('permission:manage_product_images');
    });

    // ===============================
    // Product Reviews (Authenticated)
    // ===============================
    Route::get   ('/products/{product}/reviews/me', [ProductReviewController::class, 'myReview']);
    Route::post  ('/products/{product}/reviews',    [ProductReviewController::class, 'store']);
    Route::put   ('/reviews/{review}',              [ProductReviewController::class, 'update']);
    Route::delete('/reviews/{review}',              [ProductReviewController::class, 'destroy']);
    Route::post  ('/reviews/{review}/vote',         [ProductReviewController::class, 'voteHelpful']);
    Route::delete('/reviews/{review}/vote',         [ProductReviewController::class, 'unvoteHelpful']);
    Route::post  ('/reviews/{review}/report',       [ProductReviewController::class, 'report']);

    // Moderation & Reports (Admins)
    Route::put('/reviews/{review}/status', [ProductReviewController::class, 'moderate'])
        ->middleware('permission:moderate_reviews');

    // قائمة التبليغات على المراجعات (فلترة على مستوى الموقع)
    // TODO: مستقبلاً يمكن توحيد الاسم إلى view_review_reports
    Route::get('/reviews/reports', [ProductReviewController::class, 'reportsIndex'])
        ->middleware('permission:list_reviews');

    // ===============================
    // Current user (مفيد للواجهة)
    // ===============================
    Route::get('/user', function (Request $request) {
        return $request->user()->load('roles', 'profile');
    });

    // ===============================
    // Orders (Admin)
    // ===============================
    Route::prefix('orders')->group(function () {
        Route::get('/',                 [OrderController::class, 'index'])->middleware('permission:view_orders');
        Route::get('/{order}',          [OrderController::class, 'show'])->middleware('permission:view_orders');
        Route::put('/{order}',          [OrderController::class, 'update'])->middleware('permission:edit_orders');
        Route::delete('/{order}',       [OrderController::class, 'destroy'])->middleware('permission:delete_orders');

        Route::put('/{order}/status',   [OrderController::class, 'updateStatus'])->middleware('permission:manage_order_status');
        Route::post('/{order}/cancel',  [OrderController::class, 'adminCancel'])->middleware('permission:cancel_orders');
        Route::put('/{order}/addresses',[OrderController::class, 'updateAddresses'])->middleware('permission:edit_orders');

        Route::get('/{order}/timeline', [OrderController::class, 'timeline'])->middleware('permission:view_orders');

        Route::get('/statistics/daily',   [OrderController::class, 'dailyStats'])->middleware('permission:view_order_stats');
        Route::get('/statistics/monthly', [OrderController::class, 'monthlyStats'])->middleware('permission:view_order_stats');
    });

    // ===============================
    // Customer Orders (Authenticated)
    // ===============================
    Route::prefix('my-orders')->group(function () {
        Route::get   ('/',                        [OrderController::class, 'myOrders']);
        Route::get   ('/{order}',                 [OrderController::class, 'show']);
        Route::get   ('/{order}/timeline',        [OrderController::class, 'customerTimeline']);
        Route::post  ('/{order}/cancel',          [OrderController::class, 'customerCancel']);

        // Payments (تسجيل دخول فقط)
        Route::post  ('/{order}/pay',             [PaymentController::class, 'processPayment']);
        Route::post  ('/{order}/cod-confirm',     [PaymentController::class, 'confirmCod']);
        Route::get   ('/{order}/payment-history', [PaymentController::class, 'orderPaymentHistory']);
        Route::post  ('/{order}/payments/start',  [PaymentController::class, 'start']);
        Route::get   ('/{order}/payment-methods', [PaymentController::class, 'availableMethodsForOrder']);
        Route::post  ('/{order}/refund-request',  [PaymentController::class, 'requestRefund']);
        Route::get   ('/{order}/refund-status',   [PaymentController::class, 'refundStatus']);

        // Refunds (قراءة فقط لطلبات العميل)
        Route::get   ('/{order}/refunds',         [RefundsController::class, 'customerRefunds']);
        Route::get   ('/{order}/refunds/{refund}',[RefundsController::class, 'customerRefundDetails']);

        // Invoices (Customer)
        Route::get   ('/{order}/invoices',                      [InvoicesController::class, 'byOrder']);
        Route::post  ('/{order}/invoices/{invoice}/pdf',        [InvoicesController::class, 'generatePdf']);
        Route::get   ('/{order}/invoices/{invoice}/public-url', [InvoicesController::class, 'publicUrl']);
    });

    // ===============================
    // Payment Providers (Admin)
    // ===============================
    Route::prefix('payment-providers')->group(function () {
        Route::get('/',                     [PaymentProvidersController::class, 'index'])->middleware('permission:view_payment_providers');
        Route::post('/',                    [PaymentProvidersController::class, 'store'])->middleware('permission:create_payment_providers');
        Route::get('/{paymentProvider}',    [PaymentProvidersController::class, 'show'])->middleware('permission:view_payment_providers');
        Route::put('/{paymentProvider}',    [PaymentProvidersController::class, 'update'])->middleware('permission:edit_payment_providers');
        Route::delete('/{paymentProvider}', [PaymentProvidersController::class, 'destroy'])->middleware('permission:delete_payment_providers');
        Route::post('/{paymentProvider}/activate',   [PaymentProvidersController::class, 'activate'])->middleware('permission:edit_payment_providers');
        Route::post('/{paymentProvider}/deactivate', [PaymentProvidersController::class, 'deactivate'])->middleware('permission:edit_payment_providers');
    });

    // ===============================
    // Payments (Admin)
    // ===============================
    Route::prefix('payments')->middleware('permission:manage_payments')->group(function () {
        Route::get('/',         [PaymentController::class, 'index'])->middleware('permission:view_payments');
        Route::get('/{payment}',[PaymentController::class, 'show'])->middleware('permission:view_payments');
        Route::post('/{payment}/refunds', [PaymentController::class, 'refund'])->middleware('permission:refund_payments');
    });

    // ===============================
    // Refunds (Admin) — قراءة فقط
    // ===============================
    Route::prefix('refunds')->group(function () {
        Route::get('/',        [RefundsController::class, 'index'])->middleware('permission:view_refunds');
        Route::get('/{refund}',[RefundsController::class, 'show'])->middleware('permission:view_refunds');
    });

    // ===============================
    // Customer Cart (Authenticated actions)
    // ===============================
    Route::prefix('cart')->group(function () {
        Route::post('/items',         [CartController::class, 'addItem']);
        Route::put('/items/{item}',   [CartController::class, 'updateQty']);
        Route::delete('/items/{item}',[CartController::class, 'removeItem']);
        Route::post('/clear',         [CartController::class, 'clear']);

        // Coupons on cart
        Route::post  ('/coupon/apply', [CartController::class, 'applyCoupon']);
        Route::delete('/coupon',       [CartController::class, 'removeCoupon']);
    });

    // ===============================
    // Coupons (Admin)
    // ===============================
    Route::prefix('coupons')->group(function () {
        Route::get('/',            [CouponsController::class, 'index'])->middleware('permission:view_coupons');
        Route::get('/{coupon}',    [CouponsController::class, 'show'])->middleware('permission:view_coupons');

        Route::post('/',           [CouponsController::class, 'store'])->middleware('permission:create_coupons');
        Route::put('/{coupon}',    [CouponsController::class, 'update'])->middleware('permission:edit_coupons');
        Route::delete('/{coupon}', [CouponsController::class, 'destroy'])->middleware('permission:delete_coupons');

        Route::post('/{coupon}/activate',   [CouponsController::class, 'activate'])->middleware('permission:edit_coupons');
        Route::post('/{coupon}/deactivate', [CouponsController::class, 'deactivate'])->middleware('permission:edit_coupons');

        // معاينة أثر الكوبون على سلة (إدمن)
        Route::post('/preview', [CouponsController::class, 'preview'])->middleware('permission:view_coupons');
    });

    // ===============================
    // Checkout
    // ===============================
    Route::prefix('checkout')->group(function () {
        Route::post('/carts/{cart}/place-order', [CheckoutController::class, 'placeOrder']);
        Route::put ('/orders/{order}/addresses', [CheckoutController::class, 'updateAddresses']);
    });

    // ===============================
    // Invoices (Admin)
    // ===============================
    Route::prefix('invoices')->group(function () {
        Route::get   ('/',             [InvoicesController::class, 'index'])->middleware('permission:view_invoices');
        Route::get   ('/{invoice}',    [InvoicesController::class, 'show'])->middleware('permission:view_invoices');

        Route::post  ('/{invoice}/items',     [InvoicesController::class, 'addItems'])->middleware('permission:edit_invoices');
        Route::post  ('/{invoice}/mark-paid', [InvoicesController::class, 'markPaid'])->middleware('permission:mark_paid_invoices');
        Route::post  ('/{invoice}/pdf',       [InvoicesController::class, 'generatePdf'])->middleware('permission:generate_invoice_pdf');

        Route::get   ('/{invoice}/public-url',[InvoicesController::class, 'publicUrl'])->middleware('permission:view_invoices');
    });

    // إنشاء/إصدار فاتورة من طلب (Admin)
    Route::post('/orders/{order}/invoices',       [InvoicesController::class, 'createFromOrder'])->middleware('permission:create_invoices');
    Route::post('/orders/{order}/invoices/issue', [InvoicesController::class, 'issue'])->middleware('permission:create_invoices');

    // مساعد إدارة: الكميات القابلة للفوترة (Admin)
    Route::get('/orders/{order}/invoiceable', [InvoicesController::class, 'invoiceable'])->middleware('permission:view_invoices');

    // ===============================
    // Inventory (Admin)
    // ===============================
    Route::prefix('inventory')->group(function () {
        Route::get ('/movements',   [InventoryAdjustmentsController::class, 'index'])->middleware('permission:view_inventory');
        Route::post('/adjustments', [InventoryAdjustmentsController::class, 'store'])->middleware('permission:manage_inventory');
    });

    // ===============================
    // Shipping (Admin)
    // ===============================

    // Carriers
    Route::prefix('shipping-carriers')->group(function () {
        Route::get   ('/',           [ShippingCarriersController::class, 'index'])->middleware('permission:view_shipping_carriers');
        Route::get   ('/{carrier}',  [ShippingCarriersController::class, 'show'])->middleware('permission:view_shipping_carriers');
        Route::post  ('/',           [ShippingCarriersController::class, 'store'])->middleware('permission:create_shipping_carriers');
        Route::put   ('/{carrier}',  [ShippingCarriersController::class, 'update'])->middleware('permission:edit_shipping_carriers');
        Route::delete('/{carrier}',  [ShippingCarriersController::class, 'destroy'])->middleware('permission:delete_shipping_carriers');
    });

    // Rates
    Route::prefix('shipping-rates')->group(function () {
        Route::get   ('/',        [ShippingRatesController::class, 'index'])->middleware('permission:view_shipping_rates');
        Route::get   ('/{rate}',  [ShippingRatesController::class, 'show'])->middleware('permission:view_shipping_rates');
        Route::post  ('/',        [ShippingRatesController::class, 'store'])->middleware('permission:create_shipping_rates');
        Route::put   ('/{rate}',  [ShippingRatesController::class, 'update'])->middleware('permission:edit_shipping_rates');
        Route::delete('/{rate}',  [ShippingRatesController::class, 'destroy'])->middleware('permission:delete_shipping_rates');
    });

    // Zones + Regions
    Route::prefix('shipping-zones')->group(function () {
        Route::get   ('/',                   [ShippingZonesController::class, 'index'])->middleware('permission:view_shipping_zones');
        Route::get   ('/{zone}',             [ShippingZonesController::class, 'show'])->middleware('permission:view_shipping_zones');
        Route::post  ('/',                   [ShippingZonesController::class, 'store'])->middleware('permission:create_shipping_zones');
        Route::put   ('/{zone}',             [ShippingZonesController::class, 'update'])->middleware('permission:edit_shipping_zones');
        Route::delete('/{zone}',             [ShippingZonesController::class, 'destroy'])->middleware('permission:delete_shipping_zones');

        Route::get   ('/{zone}/regions',                 [ShippingZonesController::class, 'regions'])->middleware('permission:view_shipping_zones');
        Route::post  ('/{zone}/regions',                 [ShippingZonesController::class, 'addRegion'])->middleware('permission:edit_shipping_zones');
        Route::delete('/{zone}/regions/{region}',        [ShippingZonesController::class, 'removeRegion'])->middleware('permission:edit_shipping_zones');
    });

    // Shipments
    Route::prefix('shipments')->group(function () {
        Route::get   ('/',                      [ShipmentsController::class, 'index'])->middleware('permission:view_shipments');
        Route::get   ('/{shipment}',            [ShipmentsController::class, 'show'])->middleware('permission:view_shipments');
        Route::post  ('/',                      [ShipmentsController::class, 'store'])->middleware('permission:create_shipments');
        Route::put   ('/{shipment}',            [ShipmentsController::class, 'update'])->middleware('permission:edit_shipments');

        // Events / Status operations
        Route::post  ('/{shipment}/events',         [ShipmentsController::class, 'addEvent'])->middleware('permission:manage_shipment_events');
        Route::post  ('/{shipment}/cancel',         [ShipmentsController::class, 'cancel'])->middleware('permission:cancel_shipments');
        Route::post  ('/{shipment}/mark-delivered', [ShipmentsController::class, 'markDelivered'])->middleware('permission:mark_delivered_shipments');

        // Items inside a shipment
        Route::post  ('/{shipment}/items', [ShipmentItemsController::class, 'store'])->middleware('permission:manage_shipment_items');
    });

    // Shipment items by id
    Route::put   ('/shipment-items/{item}',   [ShipmentItemsController::class, 'updateQty'])->middleware('permission:manage_shipment_items');
    Route::delete('/shipment-items/{item}',   [ShipmentItemsController::class, 'destroy'])->middleware('permission:manage_shipment_items');

    // ===============================
    // Settings (Admin)
    // ===============================
    Route::prefix('settings')->group(function () {
        Route::get ('/',        [SettingsController::class, 'index'])->middleware('permission:view_settings');
        Route::post('/batch',   [SettingsController::class, 'upsertMany'])->middleware('permission:edit_settings');

        // مفاتيح بشكل group.key (يسمح بالنقطة)
        Route::get ('/{fullKey}', [SettingsController::class, 'show'])
            ->where('fullKey', '.*')
            ->middleware('permission:view_settings');

        Route::put ('/{fullKey}', [SettingsController::class, 'update'])
            ->where('fullKey', '.*')
            ->middleware('permission:edit_settings');
    });
    
/*
|--------------------------------------------------------------------------
| Blog — Admin 
|--------------------------------------------------------------------------
*/
Route::prefix('blog')->group(function () {
    // ===== Posts =====
    Route::get   ('/posts',                 [BlogPostsController::class, 'adminIndex'])->middleware('permission:view_blog_posts');
    Route::post  ('/posts',                 [BlogPostsController::class, 'adminStore'])->middleware('permission:create_blog_posts');
    Route::put   ('/posts/{post}',          [BlogPostsController::class, 'adminUpdate'])->middleware('permission:edit_blog_posts');
    Route::delete('/posts/{post}',          [BlogPostsController::class, 'adminDestroy'])->middleware('permission:delete_blog_posts');
    Route::post  ('/posts/{post}/publish',  [BlogPostsController::class, 'adminPublish'])->middleware('permission:publish_blog_posts');
    Route::post  ('/posts/{post}/unpublish',[BlogPostsController::class, 'adminUnpublish'])->middleware('permission:publish_blog_posts');

    // ===== Categories =====
    Route::get   ('/categories',            [BlogCategoriesController::class, 'adminIndex'])->middleware('permission:view_blog_categories');
    Route::post  ('/categories',            [BlogCategoriesController::class, 'adminStore'])->middleware('permission:create_blog_categories');
    Route::put   ('/categories/{category}', [BlogCategoriesController::class, 'adminUpdate'])->middleware('permission:edit_blog_categories');
    Route::delete('/categories/{category}', [BlogCategoriesController::class, 'adminDestroy'])->middleware('permission:delete_blog_categories');

    // ===== Tags =====
    Route::get   ('/tags',                  [BlogTagsController::class, 'adminIndex'])->middleware('permission:view_blog_tags');
    Route::post  ('/tags',                  [BlogTagsController::class, 'adminStore'])->middleware('permission:create_blog_tags');
    Route::put   ('/tags/{tag}',            [BlogTagsController::class, 'adminUpdate'])->middleware('permission:edit_blog_tags');
    Route::delete('/tags/{tag}',            [BlogTagsController::class, 'adminDestroy'])->middleware('permission:delete_blog_tags');

    // ===== Comments (Moderation) =====
    Route::get   ('/comments',              [BlogCommentsController::class, 'adminIndex'])->middleware('permission:view_blog_comments');
    Route::put   ('/comments/{comment}/moderate', [BlogCommentsController::class, 'adminModerate'])->middleware('permission:moderate_blog_comments');
    Route::delete('/comments/{comment}',    [BlogCommentsController::class, 'adminDestroy'])->middleware('permission:delete_blog_comments');

    // ===== Media =====
    Route::get   ('/posts/{post}/media',    [BlogMediaController::class, 'adminIndex'])->middleware('permission:edit_blog_posts');
    Route::post  ('/posts/{post}/media',    [BlogMediaController::class, 'adminStore'])->middleware('permission:edit_blog_posts');
    Route::put   ('/media/{image}',         [BlogMediaController::class, 'adminUpdate'])->middleware('permission:edit_blog_posts');
    Route::delete('/media/{image}',         [BlogMediaController::class, 'adminDestroy'])->middleware('permission:edit_blog_posts');
});

});

/*
|--------------------------------------------------------------------------
| Fallback
|--------------------------------------------------------------------------
*/
Route::fallback(function () {
    return response()->json([
        'success'     => false,
        'message'     => 'Route not found',
        'status_code' => 404,
    ], 404);
});
