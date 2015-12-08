<?php
namespace nevmerzhitsky\AdblockUtils;

/**
 * Work with EasyList file for AdBlock Plus.
 *
 * @link https://adblockplus.org/en/filters
 */
class EasyListParser {

    /**
     * @param string $path
     * @return \Generator
     */
    public function blackListGenerator ($path) {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        if (($fp = fopen($path, 'r')) === false) {
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

            yield $line;
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
     */
    protected function _convertFilterToUrlPattern ($line, array $options = []) {
        if (empty($line) || strlen($line) < 2) {
            return null;
        }

        // It's not a regular expression.
        if ('/' != $line[0] || substr($line, -1) != '/') {
            // Element hiding feature cannot be implemented in URL pattern.
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

            $line = preg_quote($line, '/');

            $line = str_replace('^', '([^\w\d\-\.%_]{1}|\A|\Z)', $line);
            $line = str_replace('\?', '.', $line);
            $line = str_replace('\*', '.*', $line);

            if ($startAst) {
                $line = ".*{$line}";
            }
            if ($endAst) {
                $line = "{$line}.*";
            }

            $line = "/{$line}/";

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
