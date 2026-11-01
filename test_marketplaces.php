<?php
function testFetch($url) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 8,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'header' => "Accept: text/html,application/xhtml+xml\r\nAccept-Language: en-US,en;q=0.9\r\n"
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    $h = @file_get_contents($url, false, $ctx);
    if ($h === false) {
        echo $url . " -> FAILED / BLOCKED\n";
    } else {
        echo $url . " -> SUCCESS (Length: " . strlen($h) . ")\n";
        // Check for og:image
        if (preg_match('/<meta\s+[^>]*property=[\'"]og:image[\'"][^>]*content=[\'"]([^\'"]+)[\'"]/i', $h, $m)) {
            echo "   Found og:image: " . substr($m[1], 0, 70) . "...\n";
        }
    }
}

testFetch('https://www.meesho.com');
testFetch('https://www.flipkart.com');
testFetch('https://www.amazon.in');
