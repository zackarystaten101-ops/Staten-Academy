<?php
/**
 * Migration: Replace All Pricing Plans with Standardized Plans
 * 
 * This migration:
 * 1. Deactivates all existing plans
 * 2. Creates 12 new standardized plans (4 per track)
 * 3. Fetches Stripe price IDs from product IDs
 * 4. Stores all plan data in the database
 */

require_once __DIR__ . '/../env.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../app/Services/StripeService.php';

echo "Starting migration: Replace All Pricing Plans with Standardized Plans\n";
echo "=====================================================================\n\n";

// Define all 12 standardized plans
$standardizedPlans = [
    // KIDS PLANS
    [
        'name' => 'Starter',
        'track' => 'kids',
        'price' => 140.00,
        'stripe_product_id' => 'prod_TVEp6w9u0ueFmG',
        'one_on_one_classes_per_month' => 4,
        'group_classes_per_month' => 4,
        'display_order' => 1,
        'is_best_value' => false,
        'track_specific_features' => ['Shared tutor pool', 'Google Meet classes']
    ],
    [
        'name' => 'Core',
        'track' => 'kids',
        'price' => 240.00,
        'stripe_product_id' => 'prod_TVEshtgAnQGz8W',
        'one_on_one_classes_per_month' => 8,
        'group_classes_per_month' => 6,
        'display_order' => 2,
        'is_best_value' => false,
        'track_specific_features' => ['Assigned tutor', 'Google Meet classes', 'Email support']
    ],
    [
        'name' => 'Intensive',
        'track' => 'kids',
        'price' => 420.00,
        'stripe_product_id' => 'prod_TVEu0yBvaETn0l',
        'one_on_one_classes_per_month' => 13,
        'group_classes_per_month' => 12,
        'display_order' => 3,
        'is_best_value' => false,
        'track_specific_features' => ['Assigned tutor', 'Google Meet classes', 'Email support', 'Monthly parent progress report']
    ],
    [
        'name' => 'Elite',
        'track' => 'kids',
        'price' => 520.00,
        'stripe_product_id' => 'prod_TVFbDEr2wtNc4b',
        'one_on_one_classes_per_month' => 16,
        'group_classes_per_month' => 14,
        'display_order' => 4,
        'is_best_value' => true,
        'track_specific_features' => ['Assigned tutor', 'Priority scheduling', 'Parent progress summary']
    ],
    
    // ADULTS PLANS
    [
        'name' => 'Starter',
        'track' => 'adults',
        'price' => 180.00,
        'stripe_product_id' => 'prod_TaXHKtqvcwLm8r',
        'one_on_one_classes_per_month' => 4,
        'group_classes_per_month' => 4,
        'display_order' => 1,
        'is_best_value' => false,
        'track_specific_features' => ['Choose your tutor', 'Google Meet classes', 'Priority support']
    ],
    [
        'name' => 'Core',
        'track' => 'adults',
        'price' => 310.00,
        'stripe_product_id' => 'prod_TaXHIPo8443qoe',
        'one_on_one_classes_per_month' => 8,
        'group_classes_per_month' => 6,
        'display_order' => 2,
        'is_best_value' => false,
        'track_specific_features' => ['Choose your tutor', 'Exclusive learning materials', 'Priority support']
    ],
    [
        'name' => 'Intensive',
        'track' => 'adults',
        'price' => 540.00,
        'stripe_product_id' => 'prod_TaXIVLhV3mJ9Vi',
        'one_on_one_classes_per_month' => 13,
        'group_classes_per_month' => 12,
        'display_order' => 3,
        'is_best_value' => false,
        'track_specific_features' => ['Choose your tutor', 'Exclusive learning materials', 'Dedicated support', 'Personal learning plan']
    ],
    [
        'name' => 'Elite',
        'track' => 'adults',
        'price' => 670.00,
        'stripe_product_id' => 'prod_TaXJmkiM06H87U',
        'one_on_one_classes_per_month' => 16,
        'group_classes_per_month' => 14,
        'display_order' => 4,
        'is_best_value' => true,
        'track_specific_features' => ['Choose your tutor', 'Dedicated support', 'Priority scheduling', 'Monthly progress consultation']
    ],
    
    // CODING PLANS
    [
        'name' => 'Starter',
        'track' => 'coding',
        'price' => 215.00,
        'stripe_product_id' => 'prod_TaXgiMD8WMa8I6',
        'one_on_one_classes_per_month' => 4,
        'group_classes_per_month' => 4,
        'display_order' => 1,
        'is_best_value' => false,
        'track_specific_features' => ['Technical English focus']
    ],
    [
        'name' => 'Core',
        'track' => 'coding',
        'price' => 370.00,
        'stripe_product_id' => 'prod_TaXhhb7Ja2LlrH',
        'one_on_one_classes_per_month' => 8,
        'group_classes_per_month' => 6,
        'display_order' => 2,
        'is_best_value' => false,
        'track_specific_features' => ['Assigned tutor', 'Email support']
    ],
    [
        'name' => 'Intensive',
        'track' => 'coding',
        'price' => 640.00,
        'stripe_product_id' => 'prod_TaXiP65eTQop8s',
        'one_on_one_classes_per_month' => 13,
        'group_classes_per_month' => 12,
        'display_order' => 3,
        'is_best_value' => false,
        'track_specific_features' => ['Assigned tutor', 'Email support', 'Technical vocabulary roadmap']
    ],
    [
        'name' => 'Elite',
        'track' => 'coding',
        'price' => 790.00,
        'stripe_product_id' => 'prod_TaXjihuxFOcR4T',
        'one_on_one_classes_per_month' => 16,
        'group_classes_per_month' => 14,
        'display_order' => 4,
        'is_best_value' => true,
        'track_specific_features' => ['Assigned tutor', 'Priority scheduling', 'Real-world coding communication practice']
    ]
];

try {
    // Step 1: Deactivate all existing plans
    echo "Step 1: Deactivating all existing plans...\n";
    $deactivateResult = $conn->query("UPDATE subscription_plans SET is_active = FALSE");
    if ($deactivateResult) {
        $affected = $conn->affected_rows;
        echo "   ✓ Deactivated $affected existing plan(s)\n\n";
    } else {
        echo "   ⚠ Warning: Could not deactivate existing plans: " . $conn->error . "\n\n";
    }
    
    // Step 2: Initialize Stripe Service
    echo "Step 2: Initializing Stripe Service...\n";
    $stripeService = new StripeService($conn);
    echo "   ✓ Stripe Service initialized\n\n";
    
    // Step 3: Fetch price IDs from Stripe and insert plans
    echo "Step 3: Creating standardized plans...\n";
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($standardizedPlans as $planData) {
        $planName = $planData['name'];
        $track = $planData['track'];
        $productId = $planData['stripe_product_id'];
        
        echo "   Processing: {$track} - {$planName} (Product: {$productId})...\n";
        
        // Fetch price ID from Stripe
        $priceId = null;
        try {
            $priceId = $stripeService->getPriceIdFromProductId($productId);
            if ($priceId) {
                echo "      ✓ Found price ID: {$priceId}\n";
            } else {
                echo "      ⚠ Warning: No price ID found for product {$productId}\n";
            }
        } catch (Exception $e) {
            echo "      ✗ Error fetching price ID: " . $e->getMessage() . "\n";
        }
        
        // Prepare description
        $description = "Monthly subscription plan for {$track} track - {$planName} tier";
        
        // Prepare features JSON
        $featuresJson = json_encode([
            'one_on_one_classes_per_month' => $planData['one_on_one_classes_per_month'],
            'group_classes_per_month' => $planData['group_classes_per_month'],
            'session_duration_minutes' => 50,
            'includes_one_on_one' => true,
            'includes_group_classes' => true,
            'track_specific_features' => $planData['track_specific_features']
        ]);
        
        // Prepare track_specific_features JSON
        $trackFeaturesJson = json_encode($planData['track_specific_features']);
        
        // Insert plan into database
        $stmt = $conn->prepare("
            INSERT INTO subscription_plans (
                name, description, price, stripe_product_id, stripe_price_id,
                track, one_on_one_classes_per_month, group_classes_per_month,
                group_classes_included, track_specific_features, is_best_value,
                display_order, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?, ?, ?, TRUE, NOW())
        ");
        
        $stmt->bind_param(
            "ssdsssiisbi",
            $planData['name'],
            $description,
            $planData['price'],
            $productId,
            $priceId,
            $planData['track'],
            $planData['one_on_one_classes_per_month'],
            $planData['group_classes_per_month'],
            $trackFeaturesJson,
            $planData['is_best_value'],
            $planData['display_order']
        );
        
        if ($stmt->execute()) {
            $successCount++;
            echo "      ✓ Plan created successfully (ID: {$conn->insert_id})\n";
        } else {
            $errorCount++;
            echo "      ✗ Error creating plan: " . $stmt->error . "\n";
        }
        
        $stmt->close();
        echo "\n";
        
        // Small delay to avoid rate limiting
        usleep(200000); // 0.2 seconds
    }
    
    echo "\n";
    echo "=====================================================================\n";
    echo "Migration Summary:\n";
    echo "  ✓ Successfully created: {$successCount} plan(s)\n";
    if ($errorCount > 0) {
        echo "  ✗ Errors: {$errorCount} plan(s)\n";
    }
    echo "=====================================================================\n";
    echo "\nMigration completed!\n";
    
} catch (Exception $e) {
    echo "\n";
    echo "=====================================================================\n";
    echo "ERROR: Migration failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "=====================================================================\n";
    exit(1);
}

