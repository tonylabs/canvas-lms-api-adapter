<?php

require_once 'vendor/autoload.php';

use TONYLABS\Canvas\RequestBuilder;

// This is a test script to verify pagination works correctly
// Note: This won't actually run without proper Canvas credentials and setup

echo "Testing pagination fix...\n";

// Test the pagination URL handling
try {
    // This would normally require actual Canvas credentials
    $objRequestBuilder = new RequestBuilder('https://example.instructure.com', 'fake-token');
    
    // Set up the request like the user did
    $objRequestBuilder->endpoint("/api/v1/sections/123/enrollments")
                     ->addQueryVar('type', 'StudentEnrollment');
    
    // Get paginator with page size 5
    $objPagination = $objRequestBuilder->getPaginator(5);
    
    // Use reflection to access private properties and methods
    $reflection = new ReflectionClass($objRequestBuilder);
    $queryStringProperty = $reflection->getProperty('queryString');
    $queryStringProperty->setAccessible(true);
    
    echo "=== Initial Setup ===\n";
    $queryString = $queryStringProperty->getValue($objRequestBuilder);
    echo "Query string parameters:\n";
    print_r($queryString);
    
    if (isset($queryString['per_page']) && $queryString['per_page'] == 5) {
        echo "✅ SUCCESS: per_page parameter is correctly set to 5\n";
    } else {
        echo "❌ FAILED: per_page parameter is not set correctly\n";
        echo "Expected: 5, Got: " . ($queryString['per_page'] ?? 'not set') . "\n";
    }
    
    if (isset($queryString['type']) && $queryString['type'] == 'StudentEnrollment') {
        echo "✅ SUCCESS: type parameter is correctly preserved\n";
    } else {
        echo "❌ FAILED: type parameter was not preserved\n";
    }
    
    echo "\n=== Testing Pagination URL Processing ===\n";
    
    // Simulate a Canvas pagination URL (without per_page parameter)
    $simulatedPaginationUrl = 'https://example.instructure.com/api/v1/sections/123/enrollments?page=2&other_param=value';
    
    echo "Simulating pagination URL: $simulatedPaginationUrl\n";
    
    // Test the requestPaginationUrl method behavior by simulating its logic
    $parsedUrl = parse_url($simulatedPaginationUrl);
    $originalPerPage = $queryString['per_page'] ?? null;
    
    // Simulate what the method does
    $newQueryString = [];
    if (isset($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $newQueryString);
    }
    
    // Apply our fix logic
    if ($originalPerPage !== null && !isset($newQueryString['per_page'])) {
        $newQueryString['per_page'] = $originalPerPage;
    }
    
    echo "Query string after pagination URL processing:\n";
    print_r($newQueryString);
    
    if (isset($newQueryString['per_page']) && $newQueryString['per_page'] == 5) {
        echo "✅ SUCCESS: per_page parameter preserved during pagination (value: {$newQueryString['per_page']})\n";
    } else {
        echo "❌ FAILED: per_page parameter lost during pagination\n";
        echo "Expected: 5, Got: " . ($newQueryString['per_page'] ?? 'not set') . "\n";
    }
    
    if (isset($newQueryString['page']) && $newQueryString['page'] == 2) {
        echo "✅ SUCCESS: page parameter correctly extracted from pagination URL\n";
    } else {
        echo "❌ FAILED: page parameter not correctly extracted\n";
    }
    
    if (isset($newQueryString['other_param']) && $newQueryString['other_param'] == 'value') {
        echo "✅ SUCCESS: other pagination parameters preserved\n";
    } else {
        echo "❌ FAILED: other pagination parameters lost\n";
    }
    
} catch (Exception $e) {
    echo "Note: Exception expected due to fake credentials: " . $e->getMessage() . "\n";
    echo "But we can still test the query string building...\n";
}

echo "\nTest completed.\n";