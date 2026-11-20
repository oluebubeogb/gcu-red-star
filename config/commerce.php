<?php
return [
    // paystack (default) or monnify. Paid features are hidden unless the selected gateway's keys are set.
    'method' => strtolower((string) env('PAYMENT_METHOD', 'paystack')),
    // Paystack (https://paystack.com/docs/api). Amounts are in kobo (₦1 = 100 kobo).
    'paystack' => [
        'secret' => env('PAYSTACK_SECRET_KEY'),
        'public' => env('PAYSTACK_PUBLIC_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],
    // Monnify (https://developers.monnify.com). Init amount is Naira; we convert from kobo. Default base is sandbox.
    'monnify' => [
        'api_key' => env('MONNIFY_API_KEY'),
        'secret' => env('MONNIFY_SECRET_KEY'),
        'contract_code' => env('MONNIFY_CONTRACT_CODE'),
        'base_url' => rtrim((string) env('MONNIFY_BASE_URL', 'https://sandbox.monnify.com'), '/'),
    ],
    'currency' => 'NGN',
    // Defaults; HQ can change prices (stored in settings / ad_slots / sponsorship_packages).
    'featured_month_kobo' => (int) env('REDSTAR_FEATURED_MONTH_KOBO', 500000), // ₦5,000 per month
    'business_categories' => ['Agriculture', 'Automotive', 'Construction & Real Estate', 'Consulting', 'Education', 'Energy & Oil/Gas',
        'Engineering', 'Fashion & Beauty', 'Finance & Insurance', 'Food & Hospitality', 'Health & Medical', 'ICT & Software',
        'Legal', 'Logistics & Transport', 'Manufacturing', 'Media & Creative', 'Retail & Trading', 'Other'],
    'states' => ['Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 'Cross River', 'Delta', 'Ebonyi',
        'Edo', 'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos',
        'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara', 'Outside Nigeria'],
];
