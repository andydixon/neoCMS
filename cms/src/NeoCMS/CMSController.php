<?php
namespace NeoCMS;

class CMSController
{
    private array $config;
    private Authentication $authentication;
    private Logger $logger;
    private array $request;
    private string $templatesDir;
    private string $documentRoot;

    public function __construct($config)
    {
        $this->config = $config;
        $this->authentication = new Authentication($config['authentication']);
        $this->logger = new Logger($config['audit']);
        $this->request = $_REQUEST;
        $this->templatesDir = realpath(__DIR__ . '/../../templates/') . DIRECTORY_SEPARATOR;
        $this->documentRoot = rtrim(realpath($_SERVER['DOCUMENT_ROOT']), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Handle an AJAX call
     * @return void
     */
    public function handleRequest(): void
    {
        $action = $this->request['action'] ?? null;

        if (!$action) {
            $this->sendJsonResponse(['error' => 'No action specified']);
            return;
        }

        // Check if the user is authenticated for actions that require it
        if (in_array($action, ['save', 'newPage']) && !$this->authentication->isLoggedIn()) {
            $this->sendJsonResponse(['error' => 'Not authenticated']);
            return;
        }

        $methodName = $action . 'Action';
        if (method_exists($this, $methodName)) {
            try {
                $this->$methodName();
            } catch (\Exception $e) {
                $this->sendJsonResponse(['error' => $e->getMessage()]);
            }
        } else {
            $this->sendJsonResponse(['error' => 'Unknown action']);
        }
    }

    /**
     * Saves a page to the filesystem
     * @return void
     * @throws \Exception
     */
    private function saveAction(): void
    {
        $this->ensurePostRequest();
        $uri = $_POST['uri'] ?? '';
        $content = $_POST['content'] ?? '';

        if (empty($uri) || empty($content)) {
            $this->sendJsonResponse(['error' => 'URI and content are required']);
            return;
        }

        $filename = $this->saveContent($uri, $content);
        $this->sendJsonResponse([
            'message' => 'Page has been saved',
            'destination' => $filename,
        ]);
    }

    /**
     * Returns back a JSON object containing templates
     * @return void
     */
    private function getTemplatesAction(): void
    {
        $files = array_diff(scandir($this->templatesDir), ['..', '.']);
        $templates = [];

        foreach ($files as $file) {
            $templates[] = [
                'id' => $file,
                'name' => pathinfo($file, PATHINFO_FILENAME),
            ];
        }

        $this->sendJsonResponse($templates);
    }

    /**
     * Create a new page based on a template file
     * @return void
     * @throws \Exception
     */
    private function newPageAction(): void
    {
        $this->ensurePostRequest();
        $filename = $_POST['filename'] ?? '';
        $template = $_POST['template'] ?? '';

        $filename = $this->sanitiseFileName($filename);
        $template = $this->sanitiseFileName($template);

        if (!$filename || !$template) {
            $this->sendJsonResponse(['error' => 'Filename and template are required']);
            return;
        }

        if (!preg_match('/\.(html|htm)$/', $filename)) {
            $filename .= '.html';
        }

        $destination = $this->documentRoot . ltrim($filename, DIRECTORY_SEPARATOR);
        $templatePath = realpath($this->templatesDir . $template);

        if (!$templatePath || strpos($templatePath, $this->templatesDir) !== 0) {
            $this->sendJsonResponse(['error' => 'Template not found']);
            return;
        }

        if (file_exists($destination)) {
            $this->sendJsonResponse(['error' => 'Page or file already exists']);
            return;
        }

        if (!copy($templatePath, $destination)) {
            $this->sendJsonResponse(['error' => 'Template copy failed']);
            return;
        }

        $this->logger->write(
            "Created new page: $filename from template $template",
            $this->authentication->getLoggedInUser()
        );

        $this->sendJsonResponse([
            'ok' => 'DONE_THE_NEEDFUL',
            'url' => '/' . ltrim($filename, '/'),
        ]);
    }

    /**
     * Get list of pages and return as a JSON response
     * @return void
     */
    private function getPagesAction(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->documentRoot)
        );
        $pages = [];

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $filePath = $file->getRealPath();

            // Exclude CMS directory
            if (strpos($filePath, $this->documentRoot . 'cms' . DIRECTORY_SEPARATOR) === 0) {
                continue;
            }

            // Include only .html and .htm files
            if (preg_match('/\.(html|htm)$/i', $filePath)) {
                $relativePath = str_replace($this->documentRoot, '/', $filePath);
                $pages[] = [
                    'name' => $relativePath,
                    'url' => $relativePath,
                ];
            }
        }

        $this->sendJsonResponse($pages);
    }

    /**
     * Do the actual save to the filesystem and return back the path
     * @param $uri
     * @param $content
     * @return string
     * @throws \Exception
     */
    private function saveContent($uri, $content): string
    {
        $uri = $this->sanitiseUri($uri);
        $filePath = $this->resolveUriToFilePath($uri);

        if (file_put_contents($filePath, $content) === false) {
            throw new \Exception('Failed to save content');
        }

        $this->logger->write(
            "Content for $uri was saved.",
            $this->authentication->getLoggedInUser()
        );

        return $uri;
    }

    /**
     * Identify the file to write to
     * @param $uri
     * @return string
     * @throws \Exception
     */
    private function resolveUriToFilePath($uri): string
    {
        $path = $this->documentRoot . ltrim($uri, '/');

        if (is_dir($path)) {
            $indexFiles = ['index.html', 'index.htm'];
            foreach ($indexFiles as $indexFile) {
                if (file_exists($path . $indexFile)) {
                    $path .= $indexFile;
                    break;
                }
            }
            if (is_dir($path)) {
                throw new \Exception('No default index file found in directory: ' . $uri);
            }
        } else {
            if (!preg_match('/\.(html|htm)$/i', $path)) {
                $path .= '.html';
            }
        }

        $realPath = realpath($path);
        if (!$realPath || strpos($realPath, $this->documentRoot) !== 0) {
            throw new \Exception('Invalid file path');
        }

        return $realPath;
    }

    /**
     * Sanitise the filename
     * @param $filename
     * @return string
     */
    private function sanitiseFileName($filename): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', basename($filename));
    }

    /**
     * Sanitise a URI
     * @param $uri
     * @return string
     */
    private function sanitiseUri($uri): string
    {
        $uri = filter_var($uri, FILTER_SANITIZE_URL);
        return preg_replace('#(\.\./|/\.{2})#', '', $uri);
    }

    /**
     * Ensure that the controller is not being spammed by GET or other rubbish
     * @return void
     * @throws \Exception
     */
    private function ensurePostRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new \Exception('Invalid request method');
        }
    }

    /**
     * Send the JSON response
     * @param $data
     * @return void
     */
    private function sendJsonResponse($data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
