<?php

namespace Drupal\drmanage\Controller;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

// get AWS credentials from config
$conf = \Drupal::config('drmanage.settings');

$s3_access_key = $conf->get('s3_access_key');
$s3_secret_key = $conf->get('s3_secret_key');
$s3_bucket_location = $conf->get('s3_bucket_location');
$s3_host_bucket = $conf->get('s3_host_bucket');

putenv("AWS_ACCESS_KEY_ID=$s3_access_key");
putenv("AWS_SECRET_ACCESS_KEY=$s3_secret_key");

// initialize S3 client
$s3 = new S3Client([
    'version' => 'latest',
    'region'  => $s3_bucket_location,
]);

// query for drupal_sites nodes
$nids = \Drupal::entityQuery('node')
->condition('type', 'drupal_site')
->condition('status', NODE_PUBLISHED)
->execute();

// get current date time
$curr = new DateTime(date('Y-m-d H:i:s'));

foreach ($nids as $nid) {
    $node = \Drupal\node\Entity\Node::load($nid);
    $app_name = $node->get('field_application_name')->value;
    $month_limit = $node->get('field_keep_monthly_for')->value;
    $week_limit = $node->get('field_keep_weekly_for')->value;
    $daily_limit = $node->get('field_keep_daily_for')->value;
    $hourly_limit = $node->get('field_keep_hourly_for')->value;

    // Get backup files for app_name.
    $params = [
        'Bucket' => $s3_host_bucket,
        'Prefix' => $app_name
    ];

    do {
        // Loop until there are no more objects to retrieve.
        try {
            // Retrieves up to 1000 objects at a time.
            $result = $s3->listObjectsV2($params);
        } catch(S3Exception $e) {
            break;
        }

        for($n = 0; $n < sizeof($results['Contents']); $n++) {
            if (empty($filter) || stripos($result['Contents'][$n]['Key'], $filter) !== false) {

                $date = new DateTime($result['Contents'][$n]['LastModified']);
                $diff = $date->diff($curr);

                // Compare with monthly limit
                if ($diff->m + $diff->y * 12 > $month_limit) {
                    deleteFile($result['Contents'][$n]['Key']);
                }

                // Compare with weekly limit
                // *days returns total calculated difference in days...
                // taking into account month and year differences
                if (round($diff->days / 7 ) > $week_limit) {
                    deleteFile($result['Contents'][$n]['Key']);
                }

                // Compare with daily limit
                if ($diff->days > $daily_limit) {
                    deleteFile($result['Contents'][$n]['Key']);
                }

                // Compare with hourly limit
                if ($diff->h > $hourly_limit) {
                    deleteFile($result['Contents'][$n]['Key']);
                }
            }
        }
        $params['ContinuationToken'] = $result['NextContinuationToken'];
    } while (!empty($result['IsTruncated'])); // Will be true until there are no more objects to retrieve.

}

function deleteFile($fn) {
    // Debug - print file name
}

