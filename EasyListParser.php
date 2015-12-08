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
                list($line, $options) = explode('$', $line, 2);
                $options = $this->_parseFilterOptions($options);
            } else {
                $options = [];
            }

            // Sitekey feature cannot be implemented in URL pattern.
            if (isset($options['sitekey'])) {
                continue;
            }

            list($line, $white) = $this->_convertFilterToUrlPattern($line, $options);

            if (empty($line) || $white) {
                continue;
            }

            yield "{$line}/*";
        }

        fclose($fp);
    }

    /**
     * @param string $optString
     * @return array
     */
    protected function _parseFilterOptions ($optString) {
        $options = explode(',', $optString);

        $result = [];

        foreach ($options as $opt) {
            if (strpos($opt, '=') !== false) {
                list($opt, $val) = explode('=', $opt, 2);
            } else {
                if ('~' == $opt[0]) {
                    $opt = substr($opt, 1);
                    $val = false;
                } else {
                    $val = true;
                }
            }

            $result[$opt] = $val;
        }

        return $result;
    }

    /**
     * @param string $line
     * @param scalar[] $options
     * @return [string, boolean] Array of converted rules and boolean flag (true - exclude from filtering, false - include).
     * @link https://adblockplus.org/en/filters
     */
    protected function _convertFilterToUrlPattern ($line, array $options = []) {
        if (empty($line) || strlen($line) < 2) {
            return [
                $line,
                false
            ];
        }

        // It's not a regular expression.
        if ('/' != $line[0] || substr($line, -1) != '/') {
            // Element hiding rules.
            if (strpos($line, '##') !== false || strpos($line, '#@#') !== false) {
                return null;
            }

            $exclude = false;
            $startAst = $endAst = true;

            // Exclude from filtering.
            if (substr($line, 0, 2) == '@@') {
                $exclude = true;
                $line = substr($line, 2);
            }

            if ('|' == $line[0] && substr($line, 0, 2) != '||') {
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

            $line = '/' . preg_quote($line, '/') . '/';

            if (empty($options['match-case'])) {
                $line .= 'i';
            }
        }

        return [
            $line,
            $exclude
        ];
    }
}
