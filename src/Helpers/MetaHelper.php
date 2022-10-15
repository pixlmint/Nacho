<?php

namespace Nacho\Helpers;

use Symfony\Component\Yaml\Parser;

class MetaHelper
{
    private $metaHeaders;
    private ?Parser $yamlParser = null;

    public function parseFileMeta($rawContent, array $headers)
    {
        $pattern = "/^(?:\xEF\xBB\xBF)?(\/(\*)|---)[[:blank:]]*(?:\r)?\n"
            . "(?:(.*?)(?:\r)?\n)?(?(2)\*\/|---)[[:blank:]]*(?:(?:\r)?\n|$)/s";
        if (preg_match($pattern, $rawContent, $rawMetaMatches) && isset($rawMetaMatches[3])) {
            $meta = $this->getYamlParser()->parse($rawMetaMatches[3]) ?: array();
            $meta = is_array($meta) ? $meta : array('title' => $meta);
            if (intval($meta['title']) > 1000000000) {
                $meta['title'] = date('d.m.Y', intval($meta['title']));
            }

            foreach ($headers as $name => $key) {
                if (isset($meta[$name])) {
                    // rename field (e.g. remove whitespaces)
                    if ($key != $name) {
                        $meta[$key] = $meta[$name];
                        unset($meta[$name]);
                    }
                } elseif (!isset($meta[$key])) {
                    // guarantee array key existence
                    $meta[$key] = '';
                }
            }

            if (!empty($meta['date']) || !empty($meta['time'])) {
                // workaround for issue #336
                // Symfony YAML interprets ISO-8601 datetime strings and returns timestamps instead of the string
                // this behavior conforms to the YAML standard, i.e. this is no bug of Symfony YAML
                if (is_int($meta['date'])) {
                    $meta['time'] = $meta['date'];
                    $meta['date'] = '';
                }

                if (empty($meta['time'])) {
                    $meta['time'] = strtotime($meta['date']) ?: '';
                } elseif (empty($meta['date'])) {
                    $rawDateFormat = (date('H:i:s', $meta['time']) === '00:00:00') ? 'Y-m-d' : 'Y-m-d H:i:s';
                    $meta['date'] = date($rawDateFormat, $meta['time']);
                }
            } else {
                $meta['date'] = $meta['time'] = '';
            }
        } else {
            // guarantee array key existance
            $meta = array_fill_keys($headers, '');
        }

        return $meta;
    }

    public function getMetaHeaders()
    {
        if ($this->metaHeaders === null) {
            $this->metaHeaders = array(
                'Title' => 'title',
                'Description' => 'description',
                'Author' => 'author',
                'Date' => 'date',
                'Time' => 'time',
                'Robots' => 'robots',
                'Template' => 'template',
                'Hidden' => 'hidden'
            );
        }

        return $this->metaHeaders;
    }

    public static function createMetaString(array $meta): string
    {
        return "---" . self::implode_recursive($meta, "\n") . "\n---\n";
    }

    public function getYamlParser(): Parser
    {
        if ($this->yamlParser === null) {
            $this->yamlParser = new Parser();
        }

        return $this->yamlParser;
    }

    protected static function implode_recursive(array $arr, string $separator = ''): string
    {
        $ret = '';
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $ret .= $separator . $key . ': ' . self::implode_recursive($value, $separator);
            } else {
                $ret .= $separator . $key . ': ' . $value;
            }
        }

        return $ret;
    }
}