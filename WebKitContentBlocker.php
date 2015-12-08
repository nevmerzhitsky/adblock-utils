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
     * @return \Generator
     */
    public function getBlackList () {
        yield 'test';
    }
}
