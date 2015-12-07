<?php
namespace nevmerzhitsky\AdblockUtils;

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

            if (strpos($line, '$') !== false) {
                list($line, $params) = explode('$', $line, 2);
            } else {
                $params = '';
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

        $exclude = false;
        $startAst = $endAst = true;

        if (substr($line, 0, 2) == '@@') {
            $exclude = true;
            $line = substr($line, 2);
        }

        if ($line[0] == '|' && substr($line, 0, 2) != '||') {
            $startAst = false;
        }
        if ($line[strlen($line) - 1] == '|') {
            $endAst = false;
        }
        $line = trim($line, '|');

        $line = str_replace('^', '([^\w\d\-\.%_]{1}|\A|\Z)', $line);

        if ($startAst) {
            $line = ".*{$line}";
        }
        if ($endAst) {
            $line = "{$line}.*";
        }

        return [
            $line,
            $exclude
        ];
    }
}
