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
        /* Custom styles to hide the specific paragraph */
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
        for ($i = $links->length - 1; $i >= 0; $i--) {
            $link = $links->item($i);
            $text = $domDocument->createTextNode($link->textContent);
            $link->parentNode->replaceChild($text, $link);
        }

        $paragraphs = $domDocument->getElementsByTagName('p');
        for ($i = $paragraphs->length - 1; $i >= 0; $i--) {
            $paragraph = $paragraphs->item($i);
            if (strpos($paragraph->textContent, 'Template:Μήνες Δεκεμβρίου') !== false) {
                $paragraph->parentNode->removeChild($paragraph);
            }
        }
        
        $centers = $domDocument->getElementsByTagName('center');
        for ($i = $centers->length - 1; $i >= 0; $i--) {
            $center = $centers->item($i);
            if (strpos($center->textContent, '29 Δεκεμβρίου') !== false && strpos($center->textContent, '31 Δεκεμβρίου') !== false) {
                $center->parentNode->removeChild($center);
            }
        }
        
        $paragraphs = $domDocument->getElementsByTagName('p');
        for ($i = $paragraphs->length - 1; $i >= 0; $i--) {
            $paragraph = $paragraphs->item($i);
            if (strpos($paragraph->textContent, 'Template:ΗμερολόγιοΣεΠίνακα') !== false) {
                $paragraph->parentNode->removeChild($paragraph);
            }
        }

        $editSectionSpans = $domDocument->getElementsByTagName('span');
        for ($i = $editSectionSpans->length - 1; $i >= 0; $i--) {
            $span = $editSectionSpans->item($i);
            if ($span->getAttribute('class') === 'mw-editsection') {
                $span->parentNode->removeChild($span);
            }
        }

        $headlineSpans = $domDocument->getElementsByTagName('span');
        for ($i = $headlineSpans->length - 1; $i >= 0; $i--) {
            $span = $headlineSpans->item($i);
            if ($span->getAttribute('id') === 'Αργίες_και_εορτές') {
                $span->parentNode->removeChild($span);
            }
        }

        $spans = $domDocument->getElementsByTagName('span');
        for ($i = $spans->length - 1; $i >= 0; $i--) {
            $span = $spans->item($i);
            if ($span->getAttribute('class') === 'mw-headline' && $span->getAttribute('id') === 'Ορθόδοξη_Εκκλησία') {
                $span->parentNode->removeChild($span);
            }
        }

        $ps = $domDocument->getElementsByTagName('p');
        for ($i = $ps->length - 1; $i >= 0; $i--) {
            $p = $ps->item($i);
            if (trim($p->textContent) === 'Template:Πύλη:Ορθοδοξία/Εορτολόγιο/30 Δεκεμβρίου') {
                $p->parentNode->removeChild($p);
            }
        }

        $tables = $domDocument->getElementsByTagName('table');
        foreach ($tables as $table) {
            if ($table->hasAttribute('class') && strpos($table->getAttribute('class'), 'mbox-small ombox-notice ombox sistersitebox plainlinks') !== false) {
                $table->parentNode->removeChild($table);
            }
        }

        $spans = $domDocument->getElementsByTagName('span');
        foreach ($spans as $span) {
            if ($span->hasAttribute('class') && $span->getAttribute('class') === 'mw-headline' && $span->textContent === 'Βλέπε επίσης') {
                $span->parentNode->removeChild($span);
            }
        }

        $spans = $domDocument->getElementsByTagName('span');
        foreach ($spans as $span) {
            if ($span->hasAttribute('class') && $span->getAttribute('class') === 'mw-headline' && $span->textContent === 'Βλέπε επίσης') {
                $span->parentNode->removeChild($span);
            }
        }

        $ps = $domDocument->getElementsByTagName('p');
        foreach ($ps as $p) {
            $textContent = $p->textContent;
            if (
                strpos($textContent, '30 Νοεμβρίου - 30 Ιανουαρίου') !== false ||
                $textContent === 'Template:Μήνες' ||
                $textContent === 'Κατηγορία:Ημέρες του έτους'
            ) {
                $p->parentNode->removeChild($p);
            }
        }

        $spans = $domDocument->getElementsByTagName('span');
        foreach ($spans as $span) {
            if ($span->hasAttribute('class') && $span->getAttribute('class') === 'mw-headline' && $span->textContent === 'Βλέπε επίσης') {
                $span->parentNode->removeChild($span);
            }
        }

        $ps = $domDocument->getElementsByTagName('p');
        foreach ($ps as $p) {
            $textContent = $p->textContent;
            if (
                strpos($textContent, '30 Νοεμβρίου - 30 Ιανουαρίου') !== false ||
                $textContent === 'Template:Μήνες' ||
                $textContent === 'Κατηγορία:Ημέρες του έτους'
            ) {
                $p->parentNode->removeChild($p);
            }
        }
        
        $ps = $domDocument->getElementsByTagName('p');
        foreach ($ps as $p) {
            $textContent = $p->textContent;
            if (strpos($textContent, 'Template:Μήνες') !== false || strpos($textContent, 'Κατηγορία:Ημέρες του έτους') !== false) {
                $p->parentNode->removeChild($p);
            }
        }

        // Remove specific <p> element
        $ps = $domDocument->getElementsByTagName('p');
        foreach ($ps as $p) {
            $textContent = $p->textContent;
            if (strpos($textContent, 'Κατηγορία:Ημέρες του έτους') !== false) {
                $p->parentNode->removeChild($p);
            }
        }
		
		// Create the additional content as a DOM element
    	$additionalHtml = "<p>Από την <a href='https://el.wikipedia.org/wiki/%CE%A0%CF%8D%CE%BB%CE%B7:%CE%9A%CF%8D%CF%81%CE%B9%CE%B1' target='_blank' style='color: blue;'>Ελεύθερη Εγκυκλοπαίδεια</a></p>";

    	// Create a new DOMDocument to hold the additional HTML
    	$additionalDom = new DOMDocument();
    	$additionalDom->loadHTML(mb_convert_encoding($additionalHtml, 'HTML-ENTITIES', 'UTF-8'));

    	// Import the node to the main DOMDocument
    	$importedNode = $domDocument->importNode($additionalDom->documentElement->firstChild->firstChild, true);
    	$domDocument->documentElement->appendChild($importedNode);
		
		// Return the modified HTML content
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
