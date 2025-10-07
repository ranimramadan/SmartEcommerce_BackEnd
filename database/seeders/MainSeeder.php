<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MainSeeder extends Seeder
{
    public function run(): void
    {
        // 🔹 تنظيف الجداول قبل الإدخال
        Schema::disableForeignKeyConstraints();
        DB::table('products')->truncate();
        DB::table('brands')->truncate();
        DB::table('categories')->truncate();
        DB::table('roles')->truncate();
        DB::table('permissions')->truncate();
        DB::table('users')->truncate();
        DB::table('user_profiles')->truncate();
        DB::table('jobs')->truncate();
        DB::table('job_batches')->truncate();
        DB::table('failed_jobs')->truncate();
        DB::table('cache')->truncate();
        DB::table('cache_locks')->truncate();
        DB::table('settings')->truncate();
        DB::table('payment_providers')->truncate();
        DB::table('coupons')->truncate();
        DB::table('carts')->truncate();
        DB::table('orders')->truncate();
        DB::table('order_addresses')->truncate();
        DB::table('order_status_events')->truncate();
        DB::table('shipping_carriers')->truncate();
        DB::table('shipments')->truncate();
        DB::table('shipment_items')->truncate();
        DB::table('shipment_events')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('wishlist_items')->truncate();
        DB::table('wishlists')->truncate();
        DB::table('inventory_movements')->truncate();
        DB::table('refunds')->truncate();
        DB::table('payments')->truncate();
        DB::table('payment_intents')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        Schema::enableForeignKeyConstraints();

        /* ---------------------------
         * 1️⃣  إدخال التصنيفات (Categories)
         * --------------------------- */
        $categories = [
            [
                'name' => 'Electronics',
                'parent_id' => null,
                'description' => 'Devices, gadgets, and accessories',
                'image' => 'electronics.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Laptops',
                'parent_id' => 1,
                'description' => 'All kinds of laptops',
                'image' => 'laptops.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Smartphones',
                'parent_id' => 1,
                'description' => 'Android and iOS smartphones',
                'image' => 'smartphones.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Home Appliances',
                'parent_id' => null,
                'description' => 'Kitchen and home electronics',
                'image' => 'home_appliances.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        DB::table('categories')->insert($categories);

        /* ---------------------------
         * 2️⃣  إدخال العلامات التجارية (Brands)
         * --------------------------- */
        $brands = [
            ['name' => 'Apple', 'slug' => 'apple', 'logo' => 'apple.png', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Samsung', 'slug' => 'samsung', 'logo' => 'samsung.png', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'HP', 'slug' => 'hp', 'logo' => 'hp.png', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'LG', 'slug' => 'lg', 'logo' => 'lg.png', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('brands')->insert($brands);

        /* ---------------------------
         * 3️⃣  إدخال المنتجات (Products)
         * --------------------------- */
        $products = [
            [
                'category_id' => 2, 'brand_id' => 3, 'name' => 'HP Spectre x360',
                'slug' => Str::slug('HP Spectre x360'), 'sku' => 'HP-LAP-001',
                'price' => 1299.99, 'short_description' => 'Powerful convertible laptop with Intel Core i7',
                'long_description' => 'HP Spectre x360 with 13.3-inch display, 16GB RAM, 512GB SSD, and Windows 11.',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'category_id' => 3, 'brand_id' => 1, 'name' => 'iPhone 15 Pro',
                'slug' => Str::slug('iPhone 15 Pro'), 'sku' => 'APL-PHN-015',
                'price' => 1199.00, 'short_description' => 'Latest Apple flagship smartphone',
                'long_description' => 'iPhone 15 Pro features A17 chip, 6.1-inch OLED, and 48MP camera.',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'category_id' => 4, 'brand_id' => 4, 'name' => 'LG Smart Refrigerator',
                'slug' => Str::slug('LG Smart Refrigerator'), 'sku' => 'LG-HOME-100',
                'price' => 899.50, 'short_description' => 'Energy-efficient smart fridge with touch display',
                'long_description' => 'Spacious LG refrigerator with Wi-Fi connectivity and smart control panel.',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'category_id' => 1, 'brand_id' => 2, 'name' => 'Samsung 4K Smart TV',
                'slug' => Str::slug('Samsung 4K Smart TV'), 'sku' => 'SMG-TV-004',
                'price' => 799.00, 'short_description' => '55-inch Ultra HD Smart TV with HDR',
                'long_description' => 'Samsung Smart TV with vibrant colors, 4K resolution, and integrated streaming apps.',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ],
        ];
        DB::table('products')->insert($products);

        /* ---------------------------
         * 4️⃣  الأدوار (Roles)
         * --------------------------- */
        $roles = [
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Full access to the system', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Teacher', 'slug' => 'teacher', 'description' => 'Can manage students and courses', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Student', 'slug' => 'student', 'description' => 'Can access lessons and activities', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('roles')->insert($roles);

        /* ---------------------------
         * 5️⃣  الصلاحيات (Permissions)
         * --------------------------- */
        $permissions = [
            ['name' => 'Manage Users', 'slug' => 'manage-users', 'description' => 'Can create, edit, or delete users', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'View Reports', 'slug' => 'view-reports', 'description' => 'Can view analytical reports', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Edit Settings', 'slug' => 'edit-settings', 'description' => 'Can modify platform configuration', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('permissions')->insert($permissions);

        /* ---------------------------
         * 6️⃣  المستخدمين (Users)
         * --------------------------- */
        $users = [
            ['first_name' => 'Admin', 
            'last_name' => 'User', 
            'email' => 'admin@example.com', 
            'password' => Hash::make('password'), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['first_name' => 'John',
             'last_name' => 'Doe', 
             'email' => 'john@example.com', 
             'password' => Hash::make('password'), 
             'is_active' => true, 
             'created_at' => now(), 
             'updated_at' => now()],
            ['first_name' => 'Jane', 
             'last_name' => 'Smith', 
             'email' => 'jane@example.com', 
             'password' => Hash::make('password'), 
             'is_active' => false, 
             'created_at' => now(), 
             'updated_at' => now()],
        ];
        DB::table('users')->insert($users);

        /* ---------------------------
         * 7️⃣  الملفات الشخصية (User Profiles)
         * --------------------------- */
        $profiles = [
            ['user_id' => 1, 'address' => '123 Admin St', 'city' => 'New York', 'country' => 'USA', 'phone_number' => '123456789', 'birthdate' => '1990-01-01', 'gender' => 'male', 'profile_image' => 'admin.jpg', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 2, 'address' => '456 Elm St', 'city' => 'Los Angeles', 'country' => 'USA', 'phone_number' => '987654321', 'birthdate' => '1995-06-15', 'gender' => 'male', 'profile_image' => 'john.jpg', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 3, 'address' => '789 Pine Ave', 'city' => 'Chicago', 'country' => 'USA', 'phone_number' => '654321987', 'birthdate' => '1998-09-22', 'gender' => 'female', 'profile_image' => 'jane.jpg', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('user_profiles')->insert($profiles);

        /* ---------------------------
         * 8️⃣  الوظائف (Jobs, Job Batches, Failed Jobs)
         * --------------------------- */
        DB::table('jobs')->insert([
            [
                'queue' => 'emails',
                'payload' => json_encode(['type' => 'send_welcome_email']),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => time(),
                'created_at' => time(),
            ],
        ]);

        DB::table('job_batches')->insert([
            [
                'id' => Str::uuid(),
                'name' => 'Example Batch',
                'total_jobs' => 5,
                'pending_jobs' => 2,
                'failed_jobs' => 1,
                'failed_job_ids' => json_encode([1]),
                'options' => json_encode(['notify' => true]),
                'cancelled_at' => null,
                'created_at' => time(),
                'finished_at' => null,
            ],
        ]);

        DB::table('failed_jobs')->insert([
            [
                'uuid' => Str::uuid(),
                'connection' => 'database',
                'queue' => 'emails',
                'payload' => json_encode(['job' => 'SendEmail']),
                'exception' => 'TimeoutException',
                'failed_at' => now(),
            ],
        ]);

        /* ---------------------------
         * 9️⃣  الكاش (Cache + Locks)
         * --------------------------- */
        DB::table('cache')->insert([
            ['key' => 'site_settings', 'value' => json_encode(['theme' => 'dark']), 'expiration' => time() + 3600],
        ]);

        DB::table('cache_locks')->insert([
            ['key' => 'update_process', 'owner' => 'system', 'expiration' => time() + 120],
        ]);
                /* ---------------------------
         * 🔟 الإعدادات العامة (Settings)
         * --------------------------- */
        $settings = [
            [
                'group' => 'general',
                'key' => 'site_name',
                'value' => json_encode(['en' => 'My Shop', 'ar' => 'متجري']),
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'general',
                'key' => 'site_logo',
                'value' => json_encode(['path' => 'logo.png']),
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'ui',
                'key' => 'theme_color',
                'value' => json_encode(['primary' => '#0d6efd', 'secondary' => '#6c757d']),
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'payment',
                'key' => 'default_currency',
                'value' => json_encode(['USD']),
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        DB::table('settings')->insert($settings);

        
        /* ---------------------------
         * 11️⃣ مزودي الدفع (Payment Providers)
         * --------------------------- */
        $providers = [
            [
                'code' => 'stripe',
                'name' => 'Stripe',
                'type' => 'online',
                'config' => json_encode(['mode' => 'test', 'api_key' => 'sk_test_123']),
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'paypal',
                'name' => 'PayPal',
                'type' => 'online',
                'config' => json_encode(['mode' => 'live', 'client_id' => 'paypal_client_123']),
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'cod',
                'name' => 'Cash on Delivery',
                'type' => 'offline',
                'config' => null,
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        DB::table('payment_providers')->insert($providers);

        /* ---------------------------
         * 12️⃣ الكوبونات (Coupons)
         * --------------------------- */
        $coupons = [
            [
                'code' => 'WELCOME10',
                'type' => 'percent',
                'value' => 10,
                'max_discount' => 50,
                'min_cart_total' => 100,
                'min_items_count' => 1,
                'max_uses' => 500,
                'max_uses_per_user' => 3,
                'start_at' => now(),
                'end_at' => now()->addMonths(2),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'FREESHIP',
                'type' => 'free_shipping',
                'value' => null,
                'max_discount' => null,
                'min_cart_total' => 50,
                'min_items_count' => null,
                'max_uses' => 200,
                'max_uses_per_user' => 1,
                'start_at' => now(),
                'end_at' => now()->addMonth(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'SAVE20',
                'type' => 'amount',
                'value' => 20,
                'max_discount' => null,
                'min_cart_total' => 150,
                'min_items_count' => null,
                'max_uses' => 100,
                'max_uses_per_user' => 2,
                'start_at' => now(),
                'end_at' => now()->addMonths(3),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        DB::table('coupons')->insert($coupons);

        
        /* ---------------------------
         * 13️⃣ سلات المشتريات (Carts)
         * --------------------------- */
        $carts = [
            [
                'user_id' => 2,
                'session_id' => Str::uuid(),
                'coupon_id' => 1,
                'item_count' => 2,
                'subtotal' => 150.00,
                'discount_total' => 15.00,
                'shipping_total' => 5.00,
                'tax_total' => 10.00,
                'grand_total' => 150.00,
                'currency' => 'USD',
                'status' => 'active',
                'expires_at' => now()->addDays(3),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        DB::table('carts')->insert($carts);

        /* ---------------------------
         * 14️⃣ الطلبات (Orders)
         * --------------------------- */
        $orders = [
            [
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                'user_id' => 2,
                'cart_id' => 1,
                'coupon_id' => 1,
                'status' => 'placed',
                'payment_status' => 'paid',
                'fulfillment_status' => 'fulfilled',
                'subtotal' => 150.00,
                'discount_total' => 15.00,
                'shipping_total' => 5.00,
                'tax_total' => 10.00,
                'grand_total' => 150.00,
                'currency' => 'USD',
                'payment_provider_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                'user_id' => 3,
                'cart_id' => null,
                'coupon_id' => null,
                'status' => 'delivered',
                'payment_status' => 'paid',
                'fulfillment_status' => 'fulfilled',
                'subtotal' => 300.00,
                'discount_total' => 0.00,
                'shipping_total' => 10.00,
                'tax_total' => 15.00,
                'grand_total' => 325.00,
                'currency' => 'USD',
                'payment_provider_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        DB::table('orders')->insert($orders);


        
/* ---------------------------
 * 15️⃣ عناصر الطلبات (Order Items)
 * --------------------------- */
$orderItems = [
    [
        'order_id' => 1,
        'product_id' => 1,
        'product_variant_id' => null,
        'sku' => 'SKU-ABC123',
        'name' => 'Sample Product',
        'price' => 50.00,
        'qty' => 2,
        'line_subtotal' => 100.00,
        'line_discount' => 10.00,
        'line_total' => 90.00,
        'options' => json_encode(['color' => 'red', 'size' => 'M']),
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'order_id' => 1,
        'product_id' => 2,
        'product_variant_id' => null,
        'sku' => 'SKU-XYZ456',
        'name' => 'Another Product',
        'price' => 25.00,
        'qty' => 1,
        'line_subtotal' => 25.00,
        'line_discount' => 0,
        'line_total' => 25.00,
        'options' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ],
];
DB::table('order_items')->insert($orderItems);
          /**
           * 15️⃣ العناوين (Order Addresses)
           * --------------------------- */
        DB::table('order_addresses')->insert([
            [
                'order_id' => 1,
                'type' => 'billing',
                'first_name' => 'Hasan',
                'last_name' => 'Shahoud',
                'company' => 'MyCompany',
                'country' => 'TR',
                'state' => 'Istanbul',
                'city' => 'Fatih',
                'zip' => '34000',
                'address1' => '123 Example St',
                'address2' => 'Apt 5',
                'phone' => '+905555555555',
                'email' => 'hasan@example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => 1,
                'type' => 'shipping',
                'first_name' => 'Hasan',
                'last_name' => 'Shahoud',
                'company' => null,
                'country' => 'TR',
                'state' => 'Istanbul',
                'city' => 'Fatih',
                'zip' => '34000',
                'address1' => '123 Example St',
                'address2' => null,
                'phone' => '+905555555555',
                'email' => 'hasan@example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

          /**
           * 16️⃣ الحالات الحالية للطلب (Order Status Events)
           * --------------------------- */
        DB::table('order_status_events')->insert([
            [
                'order_id' => 1,
                'status' => 'placed',
                'note' => 'Order placed successfully',
                'happened_at' => now(),
                'changed_by_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

          /**
           * 17️⃣ شركات الشحن (Shipping Carriers)
           * --------------------------- */
        DB::table('shipping_carriers')->insert([
            [
                'code' => 'internal_fleet',
                'name' => 'Internal Fleet',
                'website' => null,
                'phone' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'dhl',
                'name' => 'DHL Express',
                'website' => 'https://www.dhl.com',
                'phone' => '+1800-225-5345',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

          /**
           * 18️⃣ الشحنات (Shipments)
           * --------------------------- */
        DB::table('shipments')->insert([
            [
                'order_id' => 1,
                'shipping_carrier_id' => 1,
                'tracking_number' => 'TRK123456',
                'status' => 'in_transit',
                'shipped_at' => now()->subDays(1),
                'delivered_at' => null,
                'failure_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

          /**
           * 19️⃣ العناصر في الشحن (Shipment Items)
           * --------------------------- */
        DB::table('shipment_items')->insert([
            [
                'shipment_id' => 1,
                'order_item_id' => 1,
                'qty' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

          /**
           * 20️⃣ الحالات الحالية للشحن (Shipment Events)
           * --------------------------- */
        DB::table('shipment_events')->insert([
            [
                'shipment_id' => 1,
                'code' => 'in_transit',
                'description' => 'Shipment left the sorting facility',
                'location' => 'Istanbul Hub',
                'happened_at' => now()->subHours(3),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'shipment_id' => 1,
                'code' => 'out_for_delivery',
                'description' => 'Courier out for delivery',
                'location' => 'Fatih, Istanbul',
                'happened_at' => now()->subHour(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
         /**
          * 21️⃣  (Payment Intents)
          * --------------------------- */
        DB::table('payment_intents')->insert([
            [
                'order_id' => 1,
                'payment_provider_id' => 1,
                'provider_payment_id' => 'pi_123ABC',
                'client_secret' => 'secret_987XYZ',
                'idempotency_key' => 'intent_key_1',
                'status' => 'requires_payment_method',
                'amount' => 120.50,
                'currency' => 'USD',
                'meta' => json_encode(['method' => 'stripe', 'test_mode' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

          /**
           * 22️⃣  (Payments)
           * --------------------------- */
        DB::table('payments')->insert([
            [
                'order_id' => 1,
                'payment_provider_id' => 1,
                'idempotency_key' => 'pay_key_1',
                'transaction_id' => 'txn_789XYZ',
                'status' => 'captured',
                'amount' => 120.50,
                'currency' => 'USD',
                'raw_response' => json_encode(['confirmation' => 'success']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => 2,
                'payment_provider_id' => 2,
                'idempotency_key' => 'pay_key_2',
                'transaction_id' => 'txn_555ABC',
                'status' => 'failed',
                'amount' => 75.00,
                'currency' => 'USD',
                'raw_response' => json_encode(['error' => 'insufficient_funds']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

          /**
           * 23️⃣  (Refunds)
           * --------------------------- */
        DB::table('refunds')->insert([
            [
                'payment_id' => 1,
                'order_id' => 1,
                'amount' => 20.00,
                'status' => 'succeeded',
                'reason' => 'Customer returned item',
                'provider_refund_id' => 'rf_12345',
                'idempotency_key' => 'refund_key_1',
                'meta' => json_encode(['refund_type' => 'partial']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

          /**
           * 24️⃣  (Inventory Movements)
           * --------------------------- */
        DB::table('inventory_movements')->insert([
            [
                'product_id' => 1,
                'product_variant_id' => null,
                'change' => -2,
                'reason' => 'order_reserved',
                'reference_type' => 'App\\Models\\Order',
                'reference_id' => 1,
                'user_id' => 1,
                'stock_before' => 10,
                'stock_after' => 8,
                'note' => 'Reserved stock for Order #1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_id' => 1,
                'product_variant_id' => null,
                'change' => +2,
                'reason' => 'order_cancelled',
                'reference_type' => 'App\\Models\\Order',
                'reference_id' => 2,
                'user_id' => 1,
                'stock_before' => 8,
                'stock_after' => 10,
                'note' => 'Restocked after cancellation',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // ❤️ wishlists
        DB::table('wishlists')->insert([
            [
                'user_id' => 1,
                'session_id' => 'sess_abc123',
                'share_token' => \Illuminate\Support\Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 2,
                'session_id' => 'sess_xyz789',
                'share_token' => \Illuminate\Support\Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // 🛍️ wishlist_items
        DB::table('wishlist_items')->insert([
            [
                'wishlist_id' => 1,
                'product_id' => 1,
                'product_variant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wishlist_id' => 1,
                'product_id' => 2,
                'product_variant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wishlist_id' => 2,
                'product_id' => 3,
                'product_variant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
 