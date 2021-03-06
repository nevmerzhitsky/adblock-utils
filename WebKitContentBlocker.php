<?php
namespace nevmerzhitsky\AdblockUtils;

/**
 * Work with WebKit Content Blocker files.
 *
 * @link https://webkit.org/blog/3476/content-blockers-first-look/
 */
class WebKitContentBlocker {

    private $_json = [];

    /**
     * @param unknown $jsonPath
     */
    public function __construct ($jsonPath) {
        if (!is_file($jsonPath) || !is_readable($jsonPath)) {
            throw new \Exception('Input JSON-file is not readable');
        }

        $json = file_get_contents($jsonPath);
        $json = json_decode($json, true);

        if (empty($json) || !is_array($json)) {
            throw new \Exception('Input JSON is not valid');
        }

        $this->_json = $json;
    }

    /**
     * @param boolean $escapeSlash Do escaping of slash char.
     * @return \Generator
     */
    public function getBlackList ($escapeSlash = false) {
        foreach ($this->_json as $rec) {
            if ('block' != $rec['action']['type']) {
                continue;
            }
            if (!empty($rec['trigger']['if-domain']) || !empty($rec['trigger']['unless-domain'])) {
                continue;
            }

            $pattern = $rec['trigger']['url-filter'];

            if (!empty($escapeSlash)) {
                $pattern = str_replace('/', '\/', $pattern);
            }

            yield $pattern;
        }
    }
}
