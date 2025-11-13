<?php
//
// Simple log parser for Assignment 7
// Reads access_log.txt and error_log.txt and produces:
//  - page_stats.csv
//  - access_timeline.csv
//  - error_timeline.csv
//  - error_types.csv
//

// -------------------------
// Load access and error logs
// -------------------------
$access_log = "/home/naliyev/public_html/bookreviews_input_site/access_log.txt";
$error_log  = "/home/naliyev/public_html/bookreviews_input_site/error_log.txt";

$access_lines = file($access_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$error_lines  = file($error_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// --------------------------------------------------
// 1) PAGE STATS: hits, unique IPs, unique browsers
// --------------------------------------------------
$pageStats = [];

foreach ($access_lines as $line) {
    // Example Apache log format:
    // IP - - [date] "GET /path HTTP/1.1" code size "browser info"
    if (preg_match('/^(\S+).*?"(?:GET|POST)\s+(\S+)/', $line, $m)) {
        $ip   = $m[1];
        $page = $m[2];

        // Extract browser (simple heuristic)
        preg_match('/"([^"]*)"\s*$/', $line, $uaMatch);
        $browser = $uaMatch[1] ?? "unknown";

        if (!isset($pageStats[$page])) {
            $pageStats[$page] = [
                "hits" => 0,
                "ips" => [],
                "browsers" => []
            ];
        }

        $pageStats[$page]["hits"]++;
        $pageStats[$page]["ips"][$ip] = true;
        $pageStats[$page]["browsers"][$browser] = true;
    }
}

// Write page_stats.csv
$fp = fopen("page_stats.csv", "w");
fwrite($fp, "page,hits,unique_ips,unique_browsers\n");
foreach ($pageStats as $page => $info) {
    fwrite($fp, "\"$page\",{$info['hits']}," . count($info['ips']) . "," . count($info['browsers']) . "\n");
}
fclose($fp);


// --------------------------------------------------
// 2) ACCESS TIMELINE: hits per hour
// --------------------------------------------------
$accessTimeline = [];

foreach ($access_lines as $line) {
    if (preg_match('/\[(\d+\/\w+\/\d+):(\d+)/', $line, $m)) {
        $bucket = $m[1] . ":" . $m[2]; // e.g. 01/Nov/2025:10
        if (!isset($accessTimeline[$bucket])) {
            $accessTimeline[$bucket] = 0;
        }
        $accessTimeline[$bucket]++;
    }
}

$fp = fopen("access_timeline.csv", "w");
fwrite($fp, "time_bucket,hits\n");
foreach ($accessTimeline as $bucket => $count) {
    fwrite($fp, "\"$bucket\",$count\n");
}
fclose($fp);


// --------------------------------------------------
// 3) ERROR TIMELINE: errors per hour
// --------------------------------------------------
$errorTimeline = [];

foreach ($error_lines as $line) {
    // Example: [Thu Nov 06 16:32:51.123456 2025]
    if (preg_match('/\[(\w+\s\w+\s\d+\s\d+)/', $line, $m)) {
        // Drop minutes+seconds → use only hour
        $time = substr($m[1], 0, -2) . "00"; // crude but enough for assignment
        if (!isset($errorTimeline[$time])) {
            $errorTimeline[$time] = 0;
        }
        $errorTimeline[$time]++;
    }
}

$fp = fopen("error_timeline.csv", "w");
fwrite($fp, "time_bucket,errors\n");
foreach ($errorTimeline as $bucket => $count) {
    fwrite($fp, "\"$bucket\",$count\n");
}
fclose($fp);


// --------------------------------------------------
// 4) ERROR TYPES: count occurrences of each type
// --------------------------------------------------
$errorTypes = [];

foreach ($error_lines as $line) {
    // Try to extract something resembling an error label
    if (preg_match('/\] (.*)$/', $line, $m)) {
        $msg = trim($m[1]);
        if ($msg === "") $msg = "unknown";

        if (!isset($errorTypes[$msg])) {
            $errorTypes[$msg] = 0;
        }
        $errorTypes[$msg]++;
    }
}

$fp = fopen("error_types.csv", "w");
fwrite($fp, "type,count\n");
foreach ($errorTypes as $type => $count) {
    // If many extremely long/unique messages appear → group them
    if ($count < 2) {
        // you can group tiny categories if needed
        continue;
    }
    fwrite($fp, "\"$type\",$count\n");
}
fclose($fp);

echo "Done. CSV files generated.\n";
?>
