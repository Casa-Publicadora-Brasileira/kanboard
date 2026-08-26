<?php

namespace Kanboard\Core\Filter;

use PicoDb\Table;
use PicoDb\Builder\ConditionBuilder;

/**
 * Class LexerParser
 *
 * Handles lexical analysis and query building for search expressions
 * with direct support for the "OR" operator (OR / or / OU / ou / ||) without needing parentheses.
 *
 * @package Kanboard\Core\Filter
 */
class LexerParser
{
    /**
     * Parse input query and execute against query Table
     *
     * @param  string $input
     * @param  Table  $query
     * @param  array  $filters
     * @param  string $defaultToken
     * @return void
     */
    public function parseAndExecute($input, Table $query, array $filters, $defaultToken = '')
    {
        if (empty($input) || trim($input) === '') {
            return;
        }

        $tokens = $this->tokenize($input, array_keys($filters));
        if (empty($tokens)) {
            return;
        }

        // Split tokens into OR groups
        $orGroups = array();
        $currentGroup = array();

        foreach ($tokens as $token) {
            if ($token['type'] === 'OR') {
                if (!empty($currentGroup)) {
                    $orGroups[] = $currentGroup;
                    $currentGroup = array();
                }
            } elseif ($token['type'] === 'AND') {
                // explicit AND is ignored because items inside a group are ANDed
                continue;
            } else {
                $currentGroup[] = $token;
            }
        }
        if (!empty($currentGroup)) {
            $orGroups[] = $currentGroup;
        }

        if (empty($orGroups)) {
            return;
        }

        // Process each group into items (merging consecutive same attributes into an implicit OR group and merging adjacent text)
        $processedGroups = array();
        foreach ($orGroups as $groupTokens) {
            $items = array();
            $count = count($groupTokens);
            for ($i = 0; $i < $count; $i++) {
                $tok = $groupTokens[$i];
                if ($tok['type'] === 'FILTER') {
                    $attr = $tok['attribute'];
                    $sameVals = array($tok['value']);
                    while ($i + 1 < $count && $groupTokens[$i + 1]['type'] === 'FILTER' && $groupTokens[$i + 1]['attribute'] === $attr) {
                        $i++;
                        $sameVals[] = $groupTokens[$i]['value'];
                    }
                    $items[] = array('type' => 'FILTER_GROUP', 'attribute' => $attr, 'values' => $sameVals);
                } elseif ($tok['type'] === 'TEXT') {
                    $text = $tok['value'];
                    while ($i + 1 < $count && $groupTokens[$i + 1]['type'] === 'TEXT') {
                        $i++;
                        $text .= ' ' . $groupTokens[$i]['value'];
                    }
                    $items[] = array('type' => 'TEXT', 'value' => $text);
                }
            }
            if (!empty($items)) {
                $processedGroups[] = $items;
            }
        }

        if (count($processedGroups) === 1) {
            $this->applyGroupItems($processedGroups[0], $query, $filters, $defaultToken);
        } else {
            $query->beginOr();
            foreach ($processedGroups as $group) {
                if (count($group) === 1) {
                    $this->applyGroupItems($group, $query, $filters, $defaultToken);
                } else {
                    $tempQuery = $this->createTempQuery($query);
                    $this->applyGroupItems($group, $tempQuery, $filters, $defaultToken);

                    $sql = preg_replace('/^\s*WHERE\s+/i', '', $tempQuery->getConditionBuilder()->build());
                    $values = $tempQuery->getConditionBuilder()->getValues();

                    if (!empty($sql)) {
                        $query->addCondition('(' . $sql . ')');
                        $this->appendValuesToQuery($query, $values);
                    }
                }
            }
            $query->closeOr();
        }
    }

    /**
     * Tokenize input string into a stream of tokens
     *
     * @param  string $input
     * @param  array  $registeredFilters
     * @return array
     */
    public function tokenize($input, array $registeredFilters)
    {
        $tokens = array();
        $offset = 0;
        $length = mb_strlen($input, 'UTF-8');
        $registeredMap = array_fill_keys(array_map('strtolower', $registeredFilters), true);

        while ($offset < $length) {
            $slice = mb_substr($input, $offset, null, 'UTF-8');

            // 1. Whitespace
            if (preg_match('/^\s+/u', $slice, $m)) {
                $offset += mb_strlen($m[0], 'UTF-8');
                continue;
            }

            // 2. Logical OR (OR, or, OU, ou, ||)
            if (preg_match('/^(OR|OU|\|\|)(?=\s|$)/ui', $slice, $m)) {
                $tokens[] = array('type' => 'OR', 'value' => $m[1]);
                $offset += mb_strlen($m[0], 'UTF-8');
                continue;
            }

            // 3. Logical AND (AND, and, &&)
            if (preg_match('/^(AND|&&)(?=\s|$)/ui', $slice, $m)) {
                $tokens[] = array('type' => 'AND', 'value' => $m[1]);
                $offset += mb_strlen($m[0], 'UTF-8');
                continue;
            }

            // 4. Registered Filter Prefix (e.g. tag:, assignee:, status:, etc.)
            if (preg_match('/^([a-zA-Z0-9_\-]+):/ui', $slice, $m)) {
                $attr = strtolower($m[1]);
                if (isset($registeredMap[$attr])) {
                    $prefixLen = mb_strlen($m[0], 'UTF-8');
                    $valueSlice = mb_substr($slice, $prefixLen, null, 'UTF-8');
                    $val = '';
                    $valLen = 0;

                    // 4a. Comparison operator + quoted string (e.g. <= "last month")
                    if (preg_match('/^([<=>]{1,2})\s*"([^"]*)"/u', $valueSlice, $vm)) {
                        $val = $vm[1] . $vm[2];
                        $valLen = mb_strlen($vm[0], 'UTF-8');
                    }
                    // 4b. Quoted string (e.g. "Foo Bar")
                    elseif (preg_match('/^"([^"]*)"/u', $valueSlice, $vm)) {
                        $val = $vm[1];
                        $valLen = mb_strlen($vm[0], 'UTF-8');
                    }
                    // 4c. Comparison operator + unquoted string (e.g. <=2026-01-01, >=now)
                    elseif (preg_match('/^([<=>]{1,2})([^\s]+)/u', $valueSlice, $vm)) {
                        $val = $vm[1] . $vm[2];
                        $valLen = mb_strlen($vm[0], 'UTF-8');
                    }
                    // 4d. Unquoted word/date/id (e.g. open, today, #123)
                    elseif (preg_match('/^([^\s]+)/u', $valueSlice, $vm)) {
                        $val = $vm[1];
                        $valLen = mb_strlen($vm[0], 'UTF-8');
                    }

                    $tokens[] = array(
                        'type' => 'FILTER',
                        'attribute' => $attr,
                        'value' => $val,
                    );
                    $offset += $prefixLen + $valLen;
                    continue;
                }
            }

            // 5. Free Text / Default Token (Quoted phrase or single word)
            if (preg_match('/^"([^"]*)"/u', $slice, $m)) {
                $tokens[] = array('type' => 'TEXT', 'value' => $m[1]);
                $offset += mb_strlen($m[0], 'UTF-8');
                continue;
            }

            if (preg_match('/^([^\s]+)/u', $slice, $m)) {
                $tokens[] = array('type' => 'TEXT', 'value' => $m[1]);
                $offset += mb_strlen($m[0], 'UTF-8');
                continue;
            }

            $offset++;
        }

        return $tokens;
    }

    /**
     * Apply a list of group items (ANDed together) to a query table
     *
     * @param  array  $items
     * @param  Table  $query
     * @param  array  $filters
     * @param  string $defaultToken
     * @return void
     */
    private function applyGroupItems(array $items, Table $query, array $filters, $defaultToken)
    {
        foreach ($items as $item) {
            if ($item['type'] === 'FILTER_GROUP') {
                $attr = $item['attribute'];
                $vals = $item['values'];
                if (isset($filters[$attr])) {
                    if (count($vals) === 1) {
                        $f = clone $filters[$attr];
                        $f->withQuery($query)->withValue($vals[0])->apply();
                    } else {
                        $query->beginOr();
                        foreach ($vals as $val) {
                            $f = clone $filters[$attr];
                            $f->withQuery($query)->withValue($val)->apply();
                        }
                        $query->closeOr();
                    }
                }
            } elseif ($item['type'] === 'TEXT') {
                if ($defaultToken !== '' && isset($filters[$defaultToken])) {
                    $f = clone $filters[$defaultToken];
                    $f->withQuery($query)->withValue($item['value'])->apply();
                }
            }
        }
    }

    /**
     * Create temporary query with clean condition builder
     *
     * @param  Table $query
     * @return Table
     */
    private function createTempQuery(Table $query)
    {
        $temp = clone $query;
        $reflector = new \ReflectionObject($temp);
        $prop = $reflector->getProperty('conditionBuilder');
        $prop->setAccessible(true);
        $dbProp = $reflector->getProperty('db');
        $dbProp->setAccessible(true);
        $prop->setValue($temp, new ConditionBuilder($dbProp->getValue($temp)));
        return $temp;
    }

    /**
     * Append bound values to query's ConditionBuilder
     *
     * @param  Table $query
     * @param  array $values
     * @return void
     */
    private function appendValuesToQuery(Table $query, array $values)
    {
        $cb = $query->getConditionBuilder();
        $reflector = new \ReflectionObject($cb);
        $prop = $reflector->getProperty('values');
        $prop->setAccessible(true);
        $currentValues = $prop->getValue($cb);
        $prop->setValue($cb, array_merge($currentValues, $values));
    }
}
