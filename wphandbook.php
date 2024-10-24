<?php

// Make sure to have the Parsedown library installed
// You can install it with Composer: composer require erusev/parsedown

require 'vendor/autoload.php';

use Parsedown;

/**
 * Class MarkdownToPHPConverter
 * 
 * Converts Markdown content to HTML using the Parsedown library.
 */
class MarkdownToPHPConverter
{
    /**
     * @var Parsedown Parsedown instance for converting Markdown to HTML.
     */
    private $parsedown;

    /**
     * Constructor initializes the Parsedown instance.
     */
    public function __construct()
    {
        echo "Initializing MarkdownToPHPConverter...\n";
        $this->parsedown = new Parsedown();
    }

    /**
     * Converts Markdown content to HTML.
     *
     * @param string $markdownContent The Markdown content to be converted.
     * @return string The converted HTML content.
     */
    public function convert($markdownContent)
    {
        echo "Converting Markdown content to HTML...\n";
        return $this->parsedown->text($markdownContent);
    }

    /**
     * Reads a Markdown file, converts its content to HTML.
     *
     * @param string $filePath The path to the Markdown file.
     * @return string The converted HTML content.
     */
    public function convertFile($filePath)
    {
        echo "Reading Markdown file: {$filePath}\n";
        if (!file_exists($filePath)) {
            echo "Warning: The specified file does not exist: {$filePath}\n";
            return "";
        }
        
        $markdownContent = file_get_contents($filePath);
        return $this->convert($markdownContent);
    }

    /**
     * Splits the Markdown content into a title and content.
     *
     * @param string $markdownContent The Markdown content to be split.
     * @return array An associative array with 'title' and 'content' keys.
     */
    public function splitTitleAndContent($markdownContent)
    {
        echo "Splitting title and content from the Markdown file...\n";
        $lines = explode("\n", $markdownContent);
        $title = array_shift($lines);
        $content = implode("\n", $lines);
        return [
            'title' => !empty($title) ? trim($title, "# ") : "",
            'content' => $this->convert($content)
        ];
    }
}

/**
 * Class MarkdownToWordPressPublisher
 * 
 * Publishes Markdown content to a WordPress site using the REST API.
 */
class MarkdownToWordPressPublisher
{
    /**
     * @var MarkdownToPHPConverter Instance of MarkdownToPHPConverter.
     */
    private $converter;

    /**
     * @var string Base URL for the WordPress API.
     */
    private $wpApiUrl;

    /**
     * @var string Username for WordPress authentication.
     */
    private $username;

    /**
     * @var string Application password for WordPress authentication.
     */
    private $applicationPassword;

    /**
     * Constructor initializes the WordPress API details and Markdown converter.
     *
     * @param string $wpApiUrl The base URL of the WordPress API.
     * @param string $username The username for authentication.
     * @param string $applicationPassword The application password for authentication.
     */
    public function __construct($wpApiUrl, $username, $applicationPassword)
    {
        echo "Initializing MarkdownToWordPressPublisher...\n";
        $this->converter = new MarkdownToPHPConverter();
        $this->wpApiUrl = rtrim($wpApiUrl, '/') . '/wp-json/wp/v2/';
        $this->username = $username;
        $this->applicationPassword = $applicationPassword;
    }

    /**
     * Publishes converted Markdown content to the specified WordPress endpoint.
     *
     * @param string $endpoint The WordPress endpoint (e.g., posts, pages).
     * @param int $contentId The ID of the content to be updated.
     * @param string $markdownContent The Markdown content to be published.
     * @param string $slug The slug for the WordPress post or page.
     * @return array The response from the WordPress API.
     * @throws Exception If the request to the WordPress API fails.
     */
    public function publishContent($endpoint, $contentId, $markdownContent, $slug)
    {
        echo "Publishing content with ID: {$contentId}\n";
        $data = $this->converter->splitTitleAndContent($markdownContent);

        $response = $this->sendRequest("{$endpoint}/{$contentId}", [
            'title' => $data['title'] ?? "",
            'content' => $data['content'] ?? "",
            'slug' => $slug
        ], 'POST');

        return $response;
    }

    /**
     * Sends a request to the WordPress API.
     *
     * @param string $endpoint The API endpoint to send the request to.
     * @param array $data The data to send in the request.
     * @param string $method The HTTP method to use (default is 'POST').
     * @return array The response from the WordPress API.
     * @throws Exception If the CURL request fails or returns an error response.
     */
    private function sendRequest($endpoint, $data, $method = 'POST')
    {
        $url = $this->wpApiUrl . $endpoint;
        echo "Sending request to WordPress API: {$url}\n";

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->applicationPassword);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('CURL request error: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        echo "HTTP response code: {$httpCode}\n";
        if ($httpCode >= 400) {
            throw new Exception('API response error: ' . $response);
        }

        curl_close($ch);

        echo "Request successful.\n";
        return json_decode($response, true);
    }
}

// Example usage
try {
    echo "Starting the publishing process...\n";
    // Load configuration from external JSON file
    $configFile = 'wphandbook.json';
    if (!file_exists($configFile)) {
        throw new Exception("The configuration file does not exist: " . $configFile);
    }
    
    $configContent = file_get_contents($configFile);
    $config = json_decode($configContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error decoding configuration file: " . json_last_error_msg());
    }

    $jsonFileUrl = $config['source_url'] ?? '';
    $wpApiUrl = $config['wordpress_domain'] ?? '';
    $username = $config['username'] ?? '';
    $applicationPassword = $config['apikey'] ?? '';

    if (empty($jsonFileUrl) || empty($wpApiUrl) || empty($username) || empty($applicationPassword)) {
        throw new Exception("Missing required configuration parameters.");
    }

    echo "Reading list of files from URL: {$jsonFileUrl}\n";
    $fileListContent = file_get_contents($jsonFileUrl);
    if ($fileListContent === false) {
        echo "Warning: The specified JSON file could not be fetched from the URL: {$jsonFileUrl}\n";
        exit;
    }
    
    $fileList = json_decode($fileListContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error decoding JSON file: " . json_last_error_msg());
    }

    // Load hash file if exists, otherwise create an empty one
    $hashFile = 'wphandbook-hash.txt';
    $hashData = [];
    if (file_exists($hashFile)) {
        $hashFileContent = file($hashFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($hashFileContent as $line) {
            list($url, $hash) = explode(',', $line);
            $hashData[$url] = $hash;
        }
    }

    $publisher = new MarkdownToWordPressPublisher($wpApiUrl, $username, $applicationPassword);

    foreach ($fileList as $item) {
        $contentId = $item['content_id'] ?? null;
        $fileUrl = $item['file_url'] ?? null;
        $endpoint = $item['endpoint'] ?? null;
        $slug = $item['slug'] ?? null;

        if (empty($contentId) || empty($fileUrl) || empty($endpoint) || empty($slug)) {
            echo "Warning: Missing required fields in manifest for one of the entries. Skipping...\n";
            continue;
        }

        echo "Processing file from URL: {$fileUrl}\n";

        // Fetch the Markdown content from GitHub URL
        $markdownContent = file_get_contents($fileUrl);
        if ($markdownContent === false) {
            echo "Warning: Could not fetch the file from URL {$fileUrl}.\n";
            continue;
        }

        // Generate hash of the current content
        $currentHash = md5($markdownContent);

        // Check if the content has changed
        if (isset($hashData[$fileUrl]) && $hashData[$fileUrl] === $currentHash) {
            echo "No changes detected for URL: {$fileUrl}. Skipping update.\n";
            continue;
        }

        // Publish the content if it has changed
        $response = $publisher->publishContent($endpoint, $contentId, $markdownContent, $slug);
        echo "Content published with ID {$contentId}: " . ($response['link'] ?? 'No link provided') . "\n";

        // Update the hash file
        $hashData[$fileUrl] = $currentHash;
    }

    // Save updated hashes back to the hash file
    $hashFileHandle = fopen($hashFile, 'w');
    foreach ($hashData as $url => $hash) {
        fwrite($hashFileHandle, $url . ',' . $hash . "\n");
    }
    fclose($hashFileHandle);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Example of wphandbook.json content
// {
//     "source_url": "https://raw.githubusercontent.com/WordPress/spain-handbook/refs/heads/main/bin/handbook-manifest.json",
//     "wordpress_domain": "https://yourwordpress.com",
//     "username": "your_username",
//     "apikey": "your_application_password"
// }

// Example of handbook manifest JSON file content
// [
//     {
//         "content_id": 123,
//         "file_url": "https://github.com/WPES/spain-handbook/blob/master/index.md",
//         "endpoint": "posts",
//         "slug": "spain-handbook"
//     },
//     {
//         "content_id": 456,
//         "file_url": "https://github.com/WPES/spain-handbook/blob/master/another-page.md",
//         "endpoint": "pages",
//         "slug": "another-page"
//     }
// ]

?>
