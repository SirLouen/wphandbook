<?php
declare(strict_types=1);

// Ensure you have the Parsedown library installed via Composer:
// composer require erusev/parsedown

require 'vendor/autoload.php';

use Parsedown;

/**
 * Class WPHandbookMarkdownConverter
 * 
 * Converts Markdown content to HTML using the Parsedown library.
 */
class WPHandbookMarkdownConverter
{
    private Parsedown $parsedown;

    /**
     * Initializes the Parsedown instance.
     */
    public function __construct()
    {
        echo "Initializing WPHandbookMarkdownConverter...\n";
        $this->parsedown = new Parsedown();
    }

    /**
     * Converts Markdown content to HTML.
     *
     * @param string $markdownContent The Markdown content to convert.
     * @return string The converted HTML content.
     */
    public function WPHandbookConvertMarkdownToHTML(string $markdownContent): string
    {
        echo "Converting Markdown to HTML...\n";
        return $this->parsedown->text($markdownContent);
    }

    /**
     * Splits Markdown content into a title and content.
     *
     * @param string $markdownContent The Markdown content to split.
     * @return array{title: string, content: string} An associative array with 'title' and 'content'.
     */
    public function WPHandbookSplitTitleAndContent(string $markdownContent): array
    {
        echo "Splitting title and content from Markdown...\n";
        $lines = explode("\n", $markdownContent);
        $titleLine = array_shift($lines);
        $title = !empty($titleLine) ? trim($titleLine, "# ") : "Untitled";
        $content = implode("\n", $lines);

        return [
            'title' => $title,
            'content' => $this->WPHandbookConvertMarkdownToHTML($content)
        ];
    }
}

/**
 * Class WPHandbookWordPressPublisher
 * 
 * Publishes Markdown content to a WordPress site using the REST API.
 */
class WPHandbookWordPressPublisher
{
    private WPHandbookMarkdownConverter $converter;
    private string $wpApiUrl;
    private string $username;
    private string $applicationPassword;
    private array $slugToIdMap = [];

    /**
     * Initializes the WordPress API details and Markdown converter.
     *
     * @param string $wpApiUrl Base URL of the WordPress API.
     * @param string $username Username for authentication.
     * @param string $applicationPassword Application password for authentication.
     */
    public function __construct(string $wpApiUrl, string $username, string $applicationPassword)
    {
        echo "Initializing WPHandbookWordPressPublisher...\n";
        $this->converter = new WPHandbookMarkdownConverter();
        $this->wpApiUrl = rtrim($wpApiUrl, '/') . '/wp-json/wp/v2/';
        $this->username = $username;
        $this->applicationPassword = $applicationPassword;
    }

    /**
     * Publishes converted Markdown content to the specified WordPress endpoint.
     *
     * @param string $endpoint The WordPress endpoint (e.g., pages).
     * @param string $markdownContent The Markdown content to publish.
     * @param string $slug The slug for the WordPress page.
     * @param string|null $parentSlug The slug of the parent page, if applicable.
     * @param int $order The menu order for the page.
     * @return array The response from the WordPress API.
     * @throws Exception If the API request fails.
     */
    public function WPHandbookPublishContent(string $endpoint, string $markdownContent, string $slug, ?string $parentSlug = null, int $order = -1): array
    {
        echo "Publishing content with slug: {$slug}\n";
        $data = $this->converter->WPHandbookSplitTitleAndContent($markdownContent);

        // Get parent ID if parentSlug is provided
        $parentId = 0;
        if ($parentSlug !== null) {
            $parentId = $this->WPHandbookGetPageIdBySlug($parentSlug) ?? 0;
            if ($parentId === 0) {
                echo "Warning: Parent page with slug '{$parentSlug}' not found. Publishing without a parent.\n";
            }
        }

        // Check if the page already exists
        $existingPage = $this->WPHandbookGetPageBySlug($slug);
        if ($existingPage) {
            $pageId = $existingPage['id'];
            echo "Existing page found with ID: {$pageId}. Updating...\n";
            $response = $this->WPHandbookSendRequest("{$endpoint}/{$pageId}", [
                'title' => $data['title'],
                'content' => $data['content'],
                'slug' => $slug,
                'parent' => $parentId,
                'menu_order' => $order
            ], 'POST');
            echo "Page updated: " . ($response['link'] ?? 'No link provided') . "\n";
        } else {
            echo "Creating new page with slug: {$slug}\n";
            $response = $this->WPHandbookSendRequest("{$endpoint}", [
                'title' => $data['title'],
                'content' => $data['content'],
                'slug' => $slug,
                'parent' => $parentId,
                'menu_order' => $order
            ], 'POST');
            echo "Page created: " . ($response['link'] ?? 'No link provided') . "\n";
        }

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
    private function WPHandbookSendRequest(string $endpoint, array $data, string $method = 'POST'): array
    {
        $url = $this->wpApiUrl . $endpoint;
        echo "Sending request to WordPress API: {$url}\n";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_USERPWD => "{$this->username}:{$this->applicationPassword}"
        ]);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

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
        return json_decode($response, true) ?? [];
    }

    /**
     * Retrieves a WordPress page by its slug.
     *
     * @param string $slug The slug of the page.
     * @return array|null The page data or null if not found.
     */
    private function WPHandbookGetPageBySlug(string $slug): ?array
    {
        // Check the cache first
        if (isset($this->slugToIdMap[$slug])) {
            return ['id' => $this->slugToIdMap[$slug]];
        }

        // Query the API for the page
        $endpoint = "pages?slug={$slug}&per_page=1";
        echo "Searching for existing page with slug: {$slug}\n";
        $response = $this->WPHandbookSendRequest($endpoint, [], 'GET');

        if (!empty($response) && is_array($response)) {
            $page = $response[0];
            $this->slugToIdMap[$slug] = $page['id'];
            return $page;
        }

        return null;
    }

    /**
     * Retrieves the ID of a WordPress page by its slug.
     *
     * @param string $slug The slug of the page.
     * @return int|null The page ID or null if not found.
     */
    private function WPHandbookGetPageIdBySlug(string $slug): ?int
    {
        $page = $this->WPHandbookGetPageBySlug($slug);
        return $page['id'] ?? null;
    }
}

// Example Usage
try {
    echo "Starting the publishing process...\n";

    // Load configuration from an external JSON file
    $configFile = 'wphandbook.json';
    if (!file_exists($configFile)) {
        throw new Exception("Configuration file does not exist: {$configFile}");
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

    echo "Fetching file list from URL: {$jsonFileUrl}\n";
    $fileListContent = file_get_contents($jsonFileUrl);
    if ($fileListContent === false) {
        echo "Warning: Could not fetch the JSON file from URL: {$jsonFileUrl}\n";
        exit;
    }

    $fileList = json_decode($fileListContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error decoding JSON file: " . json_last_error_msg());
    }

    // Load hash file if it exists, otherwise create an empty array
    $hashFile = 'wphandbook-hash.txt';
    $hashData = [];
    if (file_exists($hashFile)) {
        $hashFileContent = file($hashFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($hashFileContent as $line) {
            [$url, $hash] = explode(',', $line, 2);
            $hashData[$url] = $hash;
        }
    }

    $publisher = new WPHandbookWordPressPublisher($wpApiUrl, $username, $applicationPassword);

    foreach ($fileList as $key => $item) {
        $slug = $item['slug'] ?? null;
        $markdownUrl = $item['markdown'] ?? null;
        $parentSlug = $item['parent'] ?? null;
        $order = $item['order'] ?? 0;

        // Validate that slug and markdown are present and not empty
        if (empty($slug) || empty($markdownUrl)) {
            echo "Warning: Missing required fields (slug or markdown) in manifest entry '{$key}'. Skipping...\n";
            continue;
        }

        echo "Processing file from URL: {$markdownUrl}\n";

        // Fetch the Markdown content from the URL
        $markdownContent = file_get_contents($markdownUrl);
        if ($markdownContent === false) {
            echo "Warning: Could not fetch the Markdown file from URL: {$markdownUrl}\n";
            continue;
        }

        // Generate hash of the current content
        $currentHash = md5($markdownContent);

        // Check if the content has changed
        if (isset($hashData[$markdownUrl]) && $hashData[$markdownUrl] === $currentHash) {
            echo "No changes detected for URL: {$markdownUrl}. Skipping update.\n";
            continue;
        }

        // Publish the content if it has changed
        $response = $publisher->WPHandbookPublishContent('pages', $markdownContent, $slug, $parentSlug, $order);
        echo "Content published with slug '{$slug}': " . ($response['link'] ?? 'No link provided') . "\n";

        // Update the hash data
        $hashData[$markdownUrl] = $currentHash;
    }

    // Save updated hashes back to the hash file
    $hashFileHandle = fopen($hashFile, 'w');
    foreach ($hashData as $url => $hash) {
        fwrite($hashFileHandle, "{$url},{$hash}\n");
    }
    fclose($hashFileHandle);

    echo "Publishing process completed successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Example content of wphandbook.json
 * 
 * {
 *     "source_url": "https://raw.githubusercontent.com/WordPress/spain-handbook/main/bin/handbook-manifest.json",
 *     "wordpress_domain": "https://yourwordpress.com",
 *     "username": "your_username",
 *     "apikey": "your_application_password"
 * }
 */

/**
 * Example content of handbook-manifest.json
 * 
 * {
 *     "index": {
 *         "slug": "index",
 *         "markdown": "https://github.com/WPES/spain-handbook/blob/master/index.md",
 *         "parent": null,
 *         "order": 0
 *     },
 *     "manuales": {
 *         "slug": "manuales",
 *         "markdown": "https://github.com/WordPress/spain-handbook/blob/master/manuales/index.md",
 *         "parent": null,
 *         "order": 1
 *     },
 *     "manuales/wordpress": {
 *         "slug": "wordpress",
 *         "markdown": "https://github.com/WordPress/spain-handbook/blob/master/manuales/wordpress/index.md",
 *         "parent": "manuales",
 *         "order": 1
 *     },
 *     "manuales/otro": {
 *         "slug": "otro",
 *         "markdown": "https://github.com/WordPress/spain-handbook/blob/master/manuales/otro/index.md",
 *         "parent": "manuales",
 *         "order": 2
 *     }
 * }
 */
