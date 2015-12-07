<?php
namespace nevmerzhitsky\AdblockUtils;

/**
 * Solution to this problem: add a pipe symbol to the filter to show that there should be
 * definitely the end of the address at this point. For example the filter swf| will block
 * http://example.com/annoyingflash.swf but not http://example.com/swf/index.html.
 * And the filter |http://baddomain.example/ will block http://baddomain.example/banner.gif
 * but not http://gooddomain.example/analyze?http://baddomain.example.
 */
// Sometimes one wants to block http://example.com/banner.gif as well as https://example.com/banner.gif and http://www.example.com/banner.gif. This can be achieved by putting two pipe symbols in front of the filter which makes sure the filter matches at the beginning of the domain name: ||example.com/banner.gif will block all these addresses while not blocking http://badexample.com/banner.gif or http://gooddomain.example/analyze?http://example.com/banner.gif (requires Adblock Plus 1.1 or higher).
class EasyListParser {

    private $_filePath = '';

    public function __construct ($path) {
        $this->_filePath = $path;
    }

    /**
     * @return \Generator
     */
    public function blackListGenerator () {
        if (!is_file($this->_filePath) || !is_readable($this->_filePath)) {
            return;
        }

        if (($fp = fopen($this->_filePath, 'r')) === false) {
            return;
        }

        while (!feof($fp)) {
            $line = fgets($fp);
            $line = trim($line);

            if (strpos($line, '[Adblock') !== false) {
                continue;
            }
            if (empty($line) || '!' == $line[0]) {
                continue;
            }

            list($line, $white) = $this->_convertFilter($line);

            if (empty($line) || $white) {
                continue;
            }

            yield "{$line}/*";
        }

        fclose($fp);
    }

    /**
     * @param string $line
     * @return [string, boolean] Array of converted rules and boolean flag (true - exclude from filtering, false - include).
     * @link https://adblockplus.org/en/filters
     */
    protected function _convertFilter ($line) {
        if (empty($line) || strlen($line) < 2) {
            return $line;
        }

        $flag = false;
        $startAst = $endAst = true;

        if (substr($line, 0, 2) == '@@') {
            $flag = true;
            $line = substr($line, 2);
        }

        if ($line[0] == '|' && substr($line, 0, 2) != '||') {
            $startAst = false;
        }
        if ($line[strlen($line) - 1] == '|') {
            $endAst = false;
        }
        $line = trim($line, '|');

        if ($startAst) {
            $line = "*{$line}";
        }
        if ($endAst) {
            $line = "{$line}*";
        }

        return [
            $line,
            $flag
        ];
    }
}
