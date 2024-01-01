<?php
/**
 * Plugin Name: Historical Events
 * Description: Automatically post historical events "On This Day" using local JSON files with unique featured images daily at 06:00 AM, and categorize them under "Σαν Σήμερα".
 * Version: 1.0
 * Author: Depountis Georgios
 */

// Function to get the current date in Greek format
function getGreekDate() {
    $months = array("Ιανουαρίου", "Φεβρουαρίου", "Μαρτίου", "Απριλίου", "Μαΐου", "Ιουνίου", "Ιουλίου", "Αυγούστου", "Σεπτεμβρίου", "Οκτωβρίου", "Νοεμβρίου", "Δεκεμβρίου");
    $currentTime = current_time('timestamp');
    $monthIndex = (int)date('n', $currentTime) - 1;
    return date('j', $currentTime) . '_' . $months[$monthIndex];
}

// Function to read historical event data from a JSON file
function read_historical_event() {
    $today = getGreekDate();
    $filePath = __DIR__ . '/' . $today . '.json';

    if (!file_exists($filePath)) {
        throw new Exception('No historical event data available for today. Looking for file: ' . $filePath);
    }

    $jsonData = file_get_contents($filePath);
    return json_decode($jsonData, true);
}

// Function to parse wikitext using an API
function parse_wikitext_using_api($wikitext) {
    $url = "https://www.mediawiki.org/w/api.php";

    $postData = http_build_query([
        "action" => "parse",
        "format" => "json",
        "contentmodel" => "wikitext",
        "text" => $wikitext
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    return $responseData['parse']['text']['*'] ?? 'Error parsing wikitext';
}

// Function to add custom styles
function historical_events_custom_styles() {
    ?>
    <style type="text/css">
        .historical-event-content a {
            color: blue; /* Change the color of links to blue */
        }
    </style>
    <?php
}
add_action('wp_head', 'historical_events_custom_styles');

// Function to display historical event content
function display_historical_event($eventData) {
    if (is_array($eventData) && isset($eventData['query']['pages'][0]['revisions'][0]['slots']['main']['content'])) {
        $wikiContent = $eventData['query']['pages'][0]['revisions'][0]['slots']['main']['content'];

        // Use MediaWiki API to parse wikitext
        $htmlContent = parse_wikitext_using_api($wikiContent);

        // Load the content into a DOMDocument and remove specific paragraphs
        $domDocument = new DOMDocument();
        libxml_use_internal_errors(true);
        $domDocument->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        
		$links = $domDocument->getElementsByTagName('a');
foreach ($links as $link) {
    $href = $link->getAttribute('href');

    if (preg_match('/^\/w\/index\.php\?title=(.+?)(&action=edit&redlink=1)?$/', $href, $matches)) {
        // Construct the new URL
        $newHref = 'https://el.wikipedia.org/wiki/' . $matches[1];
        $link->setAttribute('href', $newHref);
    }
}
		// Assume $domDocument is your DOMDocument object
$spanId = 'Αργίες_και_εορτές'; // The ID of the span

// Find the specific div based on the span's ID
$xpath = new DOMXPath($domDocument);
$targetSpan = $xpath->query("//span[@id = '$spanId']")->item(0);

if ($targetSpan) {
    // Get the parent div of the span
    $divToRemove = $targetSpan;
    while ($divToRemove && $divToRemove->nodeName !== 'div') {
        $divToRemove = $divToRemove->parentNode;
    }

    // Remove the div and all elements after it
    if ($divToRemove) {
        while ($divToRemove) {
            $nextDiv = $divToRemove->nextSibling;
            $divToRemove->parentNode->removeChild($divToRemove);
            $divToRemove = $nextDiv;
        }
    }
}

// Assume $domDocument is your DOMDocument object
$xpath = new DOMXPath($domDocument);

// XPath for any <a> elements linking to a page starting with "Template:"
$templatesXPath = "//p[a[contains(@href, 'Template:')]]";

// Find all matching elements
$templateElements = $xpath->query($templatesXPath);

// Remove each matching element
foreach ($templateElements as $element) {
    $element->parentNode->removeChild($element);
}

// XPath to find the table containing the specific template link
$templateTableXPath = "//table[.//a[contains(@href, 'Template:%CE%97%CE%BC%CE%B5%CF%81%CE%BF%CE%BB%CF%8C%CE%B3%CE%B9%CE%BF%CE%A3%CE%B5%CE%A0%CE%AF%CE%BD%CE%B1%CE%BA%CE%B1')]]";

// Find the table element
$templateTableElements = $xpath->query($templateTableXPath);

// Remove the found table element
foreach ($templateTableElements as $element) {
    $element->parentNode->removeChild($element);
}
		
// // Assume $domDocument is your DOMDocument object
$xpath = new DOMXPath($domDocument);

// XPath to find all <span> elements with class 'mw-editsection'
$spanNodes = $xpath->query("//span[contains(@class, 'mw-editsection')]");

foreach ($spanNodes as $spanNode) {
    // Remove the <span> element
    $spanNode->parentNode->removeChild($spanNode);
}

		// Create the additional content as a DOM element
    	$additionalHtml = "<p>Από την <a href='https://el.wikipedia.org/wiki/%CE%A0%CF%8D%CE%BB%CE%B7:%CE%9A%CF%8D%CF%81%CE%B9%CE%B1' target='_blank' style='color: blue;'>Ελεύθερη Εγκυκλοπαίδεια</a></p>";

    	// Create a new DOMDocument to hold the additional HTML
    	$additionalDom = new DOMDocument();
    	$additionalDom->loadHTML(mb_convert_encoding($additionalHtml, 'HTML-ENTITIES', 'UTF-8'));

    	// Import the node to the main DOMDocument
    	$importedNode = $domDocument->importNode($additionalDom->documentElement->firstChild->firstChild, true);
    	$domDocument->documentElement->appendChild($importedNode);
		
		// Modify and update hyperlinks
        $links = $domDocument->getElementsByTagName('a');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');

            if (preg_match('/^\/w\/index\.php\?title=(.+?)(&action=edit&redlink=1)?$/', $href, $matches)) {
                // Construct the new URL
                $newHref = 'https://el.wikipedia.org/wiki/' . $matches[1];
                $link->setAttribute('href', $newHref);
            }

            // Set the link to open in a new tab
            $link->setAttribute('target', '_blank');
        }

        // Save the modified content
        $htmlContent = $domDocument->saveHTML();
        return "<div class='historical-event-content'>" . $htmlContent . "</div>";
    } else {
        return 'No valid event data found.'; // Fallback message
    }
}

// Function to publish historical events
function publish_historical_events() {
    $today = getGreekDate();
    try {
        $eventData = read_historical_event();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return;
    }

    $categoryId = get_or_create_category();

    $formattedDateForTitle = str_replace('_', ' ', $today);

    $post = array(
        'post_title'    => 'Σαν Σήμερα ' . $formattedDateForTitle,
        'post_content'  => display_historical_event($eventData),
        'post_status'   => 'publish',
        'post_type'     => 'post',
        'post_category' => array($categoryId)
    );

    $postID = wp_insert_post($post);

    $lastPhoto = get_option('historical_events_last_photo', '');
    $newPhoto = get_random_photo($lastPhoto);

    if ($newPhoto && $postID) {
        update_option('historical_events_last_photo', $newPhoto);

        $photoPath = __DIR__ . '/photos/' . $newPhoto;
        $upload = wp_upload_bits(basename($photoPath), null, file_get_contents($photoPath));

        if ($upload['error']) {
            throw new Exception('Error in file upload: ' . $upload['error']);
        }

        $wpFileType = wp_check_filetype($upload['file'], null);
        $attachment = array(
            'post_mime_type' => $wpFileType['type'],
            'post_title'     => sanitize_file_name($upload['file']),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        $attachmentId = wp_insert_attachment($attachment, $upload['file'], $postID);
        if (!is_wp_error($attachmentId)) {
            require_once(ABSPATH . "wp-admin" . '/includes/image.php');
            $attachmentData = wp_generate_attachment_metadata($attachmentId, $upload['file']);
            wp_update_attachment_metadata($attachmentId, $attachmentData);
            set_post_thumbnail($postID, $attachmentId);
        }
    }
}

// Function to ensure the category exists and get its ID
function get_or_create_category() {
    $categoryName = 'Σαν Σήμερα';
    $categorySlug = 'san-simera';

    $category = get_category_by_slug($categorySlug);
    if (!$category) {
        $categoryId = wp_insert_term($categoryName, 'category', array('slug' => $categorySlug));
        if (is_wp_error($categoryId)) {
            throw new Exception('Error creating category: ' . $categoryId->get_error_message());
        }
        return $categoryId['term_id'];
    }
    return $category->term_id;
}

// Function to get a random photo
function get_random_photo($exclude = '') {
    $photosPath = __DIR__ . '/photos';
    $photos = array_diff(scandir($photosPath), array('..', '.', $exclude));

    if (empty($photos)) {
        return '';
    }

    return array_rand(array_flip($photos));
}

// Function to schedule the event on plugin activation
function historical_events_activation() {
    // Temporarily set the PHP time zone to match the WordPress timezone
    date_default_timezone_set('Europe/Athens');

    // Schedule the cron job
    if (!wp_next_scheduled('historical_events_daily_post')) {
        wp_schedule_event(strtotime('06:00:00'), 'daily', 'historical_events_daily_post');
    }

    // The line to reset the timezone to UTC is now removed
}
register_activation_hook(__FILE__, 'historical_events_activation');

// Function to clear the scheduled event on plugin deactivation
function historical_events_deactivation() {
    wp_clear_scheduled_hook('historical_events_daily_post');
}
register_deactivation_hook(__FILE__, 'historical_events_deactivation');

// Hook the function to the scheduled event
add_action('historical_events_daily_post', 'publish_historical_events');

?>
