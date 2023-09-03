<?php

namespace Nacho\Helpers;

use Exception;
use Nacho\Contracts\PageHandler;
use Nacho\Contracts\SingletonInterface;
use Nacho\Core;
use Nacho\Models\PicoMeta;
use Nacho\Models\PicoPage;
use Symfony\Component\Yaml\Exception\ParseException;

class PageManager implements SingletonInterface
{
    /** @var array|PicoPage[] $pages */
    private array $pages;
    private MetaHelper $metaHelper;
    private PageSecurityHelper $pageSecurityHelper;
    private FileHelper $fileHelper;

    private static SingletonInterface|PageManager|null $instance = null;

    public function __construct()
    {
        $this->pages = [];
        $this->metaHelper = new MetaHelper();
        $this->pageSecurityHelper = new PageSecurityHelper();
        $this->fileHelper = new FileHelper();
    }

    public static function getInstance(): SingletonInterface|PageManager
    {
        if (!self::$instance) {
            self::$instance = new PageManager();
        }

        return self::$instance;
    }

    public function getPages(): array
    {
        if (!$this->pages) {
            $this->readPages();
        }

        return $this->pages;
    }

    public function getPage(string $url): ?PicoPage
    {
        $pages = $this->getPages();
        foreach ($pages as $page) {
            if (!isset($page->id)) {
                continue;
            }
            if ($page->id === $url) {
                return $page;
            }
        }

        return null;
    }

    public function renderPage(PicoPage $page): string
    {
        return $this->getPageHandler($page)->renderPage();
    }

    private function getPageHandler(PicoPage $page): PageHandler
    {
        if (isset($page->meta->renderer)) {
            return new AlternativeContentPageHandler($page);
        } else {
            return new MarkdownPageHandler($page);
        }
    }

    public function editPage(string $url, string $newContent, array $newMeta): bool
    {
        $page = $this->getPage($url);
        if (!$page) {
            throw new Exception("${url} does not exist");
        }
        $oldMeta = (array)$page->meta;
        $newMeta = array_merge($oldMeta, $newMeta);
        // Fallback for older entries that don't yet possess the owner info
        if (!$newMeta['owner']) {
            $newMeta['owner'] = Core::getUserHandler()->getCurrentUser()->getUsername();
        }

        $newPage = $page->duplicate();
        if ($newContent) {
            $newPage->raw_content = $newContent;
        }
        $newPage->meta = new PicoMeta($newMeta);

        $handler = $this->getPageHandler($newPage);
        $newPage = $handler->handleUpdate($url, $newContent, $newMeta);

        return $this->fileHelper->storePage($newPage);
    }

    public function create(string $parentFolder, string $title, bool $isFolder = false): bool
    {
        $page = $this->getPage($parentFolder);

        if (!$page) {
            throw new Exception('Unable to find this page');
        }

        $newPage = new PicoPage();
        $newPage->raw_content = 'Write Some Content';
        $meta = new PicoMeta();
        $meta->title = $title;
        $meta->date = date('Y-m-d');
        $meta->time = date('h:i:s');
        $newPage->meta = $meta;

        $contentDir = self::getContentDir();

        $parentDir = preg_replace('/index.md$/', '', $parentFolder);
        if ($isFolder) {
            // TODO: Folder names that contain a space don't work
            $directory = $contentDir . $parentDir . DIRECTORY_SEPARATOR . $title;
            mkdir($directory);
            $file = $directory . DIRECTORY_SEPARATOR . 'index.md';
            $newPage->id = $parentDir . $title;
        } else {
            $fileName = FileNameHelper::generateFileNameFromTitle($meta->title);
            $file = $contentDir . $parentDir . DIRECTORY_SEPARATOR . $fileName;
            $newPage->id = $parentDir . $fileName;
        }

        $newPage->file = $file;

        return $this->fileHelper->storePage($newPage);
    }

    public function readPages(): void
    {
        $contentDir = self::getContentDir();

        $this->pages = array();
        $files = $this->fileHelper->getFiles($contentDir);
        foreach ($files as $i => $file) {
            $id = substr($file, strlen($contentDir), -3);

            // skip inaccessible pages (e.g. drop "sub.md" if "sub/index.md" exists) by default
            $conflictFile = $contentDir . $id . '/index.md';
            $skipFile = in_array($conflictFile, $files, true) || null;

            if ($skipFile) {
                continue;
            }

            if (str_ends_with($id, '/index')) {
                $id = substr($id, 0, -6);
            }
            if (!$id) {
                $id = '/';
            }

            $url = UrlHelper::getPageUrl($id);
            $rawMarkdown = FileHelper::loadFileContent($file);
            $rawContent = $this->prepareFileContent($rawMarkdown);

            $headers = $this->metaHelper->getMetaHeaders();
            try {
                $meta = $this->metaHelper->parseFileMeta($rawMarkdown, $headers);
            } catch (ParseException $e) {
                $meta = $this->metaHelper->parseFileMeta('', $headers);
                $meta['YAML_ParseError'] = $e->getMessage();
            }

            // build page data
            $page = new PicoPage();
            $page->id = $id;
            $page->url = $url;
            $page->hidden = ($meta['hidden'] || preg_match('/(?:^|\/)_/', $id));
            $page->raw_content = $rawContent;
            $page->raw_markdown = $rawMarkdown;
            $picoMeta = new PicoMeta($meta);
            $page->meta = $picoMeta;
            $page->file = $file;
            $parentPath = explode('/', $id);
            array_pop($parentPath);
            $page->meta->parentPath = implode('/', $parentPath);

            unset($rawContent, $rawMarkdown, $meta);

            if ($this->pageSecurityHelper->isPageShowingForCurrentUser($page)) {
                $this->pages[$id] = $page;
            }
        }
    }

    /**
     * @param string $rawContent
     *
     * @return string|array|null
     */
    public static function prepareFileContent(string $rawContent): string|array|null
    {
        // remove meta header
        $metaHeaderPattern = "/^(?:\xEF\xBB\xBF)?(\/(\*)|---)[[:blank:]]*(?:\r)?\n"
            . "(?:(.*?)(?:\r)?\n)?(?(2)\*\/|---)[[:blank:]]*(?:(?:\r)?\n|$)/s";
        return preg_replace($metaHeaderPattern, '', $rawContent, 1);
    }

    public static function getContentDir(): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/content';
    }
}