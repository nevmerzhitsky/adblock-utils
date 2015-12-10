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
     * @param boolean $returnRegExps If true then returns PCRE patterns, else - wildcards.
     * @return \Generator
     */
    public function blackListGenerator ($path, $returnRegExps = false) {
        $returnRegExps = !empty($returnRegExps);

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

            // Sitekey feature cannot be implemented by URL pattern.
            if (!empty($options['sitekey'])) {
                continue;
            }
            // Cannot check document domain here.
            if (!empty($options['domain'])) {
                continue;
            }

            list($line, $white) = $this->_convertFilterToUrlPattern($line, $options, $returnRegExps);

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
     * @param boolean $returnRegExps
     * @return [string, boolean] Array of converted filters and boolean flag (true - exclude from filtering, false - include).
     */
    protected function _convertFilterToUrlPattern ($line, array $options, $returnRegExps) {
        if (empty($line) || strlen($line) < 2) {
            return null;
        }

        // Check it's a regular expression.
        if ('/' == $line[0] && substr($line, -1) == '/') {
            // Cannot convert regexp to wildcards then skip the filter.
            if (!$returnRegExps) {
                return null;
            }

            return [
                $line,
                $exclude
            ];
        }

        // Element hiding feature cannot be implemented by URL pattern.
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

        if ($returnRegExps) {
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
        } else {
            $line = rtrim($line, '^');
            // Cannot convert ^ at not end of filter to wildcards correctly.
            if (strpos($line, '^') !== false) {
                return null;
            }

            if ($startAst) {
                $line = "*{$line}";
            }
            if ($endAst) {
                $line = "{$line}*";
            }
        }

        return [
            $line,
            $exclude
        ];
    }
}
