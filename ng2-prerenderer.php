<?php

require_once __DIR__ . '/HTMLParser/parser.php';

class NG2Prerenderer {

    protected $_src_dir = '';
    protected $_templates = array();

    protected $_data = array();
    protected $_data_stack = array();


    function __construct($src) {

        $this->_src_dir = $src;
        $this->_preload($this->_src_dir);
    }


    protected function _preload($dir) {

        foreach (glob($dir . '/*') as $item) {

            if (is_file($item)) {
                if (preg_match('/.*\.component\.ts$/uis', $item)) {

                    $src = file_get_contents($item);

                    preg_match('/selector[\s]*:[\s]*(?:\'|")([^\s]+)(?:\'|")/uis', $src, $m);
                    $selector = $m[1];

                    preg_match('/templateUrl[\s]*:[\s]*(?:\'|")([^\s]+)(?:\'|")/uis', $src, $m);
                    $templateUrl = $m[1];
                    $templatePath = dirname($item) . '/' . $templateUrl;

                    $this->_templates[$selector] = $templatePath;
                }
            }
            elseif (is_dir($item)) {
                $this->_preload($item);
            }
        }
    }


    public function render($selector, $data = array()) {

        if (!isset($this->_templates[$selector])) return '';

        $html = file_get_contents($this->_templates[$selector]);

        return $this->render_html($html, $data);
    }


    public function render_html($html, $data = array()) {

        if ($data !== false ) {
            $this->_data = $data;
        }

        $parser = str_get_html($html);

        $this->_render_part($parser);

        $out = (string)$parser;

        $parser = null;

        return $out;
    }


    protected function _render_part(&$element) {

        if (isset($element->{'*ngfor'})) {

            $for_condition = $element->{'*ngfor'};
            $element->{'*ngfor'} = null;

            $html = (string)$element->outertext;

            $block = $this->_get_for_block($for_condition, $html);

            if ($block) {
                $element->outertext = $block;
            }

            return;
        }

        if (isset($element->{'*ngif'})) {

            if (!$this->_get_if_value($element->{'*ngif'})) {

                $element->outertext = '';

                return;

            } else {

                $element->{'*ngif'} = null;
            }
        }

        if (isset($this->_templates[$element->tag])) {

            // TODO: some controlled interface for passing data to subtemplates needed

            $data = $this->_data;

            array_push($this->_data_stack, $this->_data);

            foreach ($element->attr as $attr_name => $attr_var) {

                if (substr($attr_name, 0, 1) == '[' && substr($attr_name, -1) == ']') {

                    $attr_name = substr($attr_name, 1, -1);
                    $data[$attr_name] = $this->_get_var_value($attr_var);
                }
            }

            $element->outertext = $this->render($element->tag, $data);

            $this->_data = array_pop($this->_data_stack);

            return;
        }

        foreach ($element->find('text') as $text) {

            if (preg_match_all('/{{\s*([^\s][^\{\}]*[^\s])\s*}}/uis', $text->innertext, $m)) {

                $tr = array();

                foreach ($m[0] as $i => $replacement) {

                    $var = $m[1][$i];

                    if (!isset($tr[$replacement])) {
                        $tr[$replacement] = $this->_get_var_value($var, $replacement);
                    }
                }

                foreach ($tr as $from => $to) {

                    $text->innertext = str_replace($from, $to, $text->innertext);
                }
            }
        }

        if (isset($element->{'[innerhtml]'})) {

            $element->innertext = $this->_get_var_value($element->{'[innerhtml]'});
        }

        if (isset($element->{'[ngstyle]'})) {

            $style = $element->style;
            if ($style && substr($style, -1) != ';') {
                $style .= ';';
            }

            $conditions = $element->{'[ngstyle]'};
            $conditions = preg_replace('/^[\s]*{[\s]*/', '', $conditions);
            $conditions = preg_replace('/[\s]*}[\s]*$/', '', $conditions);
            $conditions = preg_split('/[\s]*,[\s]*/', $conditions);

            foreach ($conditions as $condition) {

                $tmp = explode(':', $condition);

                $name = $this->_get_var_value(array_shift($tmp));
                $value = $this->_get_var_value(join(':', $tmp));

                if (!$value) {
                    if (in_array($name, array('fill'))) {
                        $style_value = 'none';
                    }
                }

                $style .= $name . ':' . $value . ';';

            }

            $element->style = $style;
            $element->{'[ngstyle]'} = null;
        }

        if (isset($element->{'[ngclass]'})) {

            $class = $element->class;
            if (!$class) {
                $class = '';
            }

            // TODO: recognazing of data structures can be unified and improved

            $conditions = $element->{'[ngclass]'};

            if (strpos($conditions, '{') !== false) {

                $conditions = preg_replace('/^[\s]*{[\s]*/', '', $conditions);
                $conditions = preg_replace('/[\s]*}[\s]*$/', '', $conditions);
                $conditions = preg_split('/[\s]*,[\s]*/', $conditions);

                foreach ($conditions as $condition) {

                    $tmp = explode(':', $condition);

                    $name = $this->_get_var_value(array_shift($tmp));
                    $value = $this->_get_if_value(join(':', $tmp));

                    if ($value) {
                        $class .=  ' ' . $name;
                    }
                }

            } else {

                $classes = $this->_get_var_value($conditions);

                foreach ($classes as $name => $value) {

                    if ($value) {
                        $class .=  ' ' . $name;
                    }
                }
            }

            $element->class = $class;
            $element->{'[ngclass]'} = null;
        }

        if (isset($element->{'routerlink'})) {

            $element->{'href'} = $element->{'routerlink'};
            $element->{'routerlink'} = null;
        }

        $attrs = array('src', 'href', 'placeholder');

        foreach ($attrs as $attr) {

            if (isset($element->{$attr})) {

                if (preg_match_all('/{{\s*([^\s].*[^\s])\s*}}/uis', $element->{$attr}, $m)) {

                    $tr = array();

                    foreach ($m[0] as $i => $replacement) {

                        $var = $m[1][$i];

                        if (!isset($tr[$replacement])) {
                            $tr[$replacement] = $this->_get_var_value($var);
                        }
                    }

                    foreach ($tr as $from => $to) {

                        $element->{$attr} = str_replace($from, $to, $element->{$attr});
                    }
                }
            }
        }

        foreach ($element->childNodes() as $child) {

            $this->_render_part($child);
        }
    }


    protected function _get_if_value($condition) {

        $rv = false;

        $condition = trim($condition);

        // TODO: VERY ugly, the whole system of values parsing should be redesigned

        $condition = preg_replace('/\.indexOf\(([^\)]+)\)/', '.indexOf_\1_', $condition);

        if (preg_match_all('/(?:^|[\(\s]|!)([a-zA-Z\_][a-zA-Z0-9\.\_\'\"]*)(?:[\)\s]|$)/uis', $condition, $m)) {

            $tr = array();

            foreach ($m[1] as $var) {

                if (!isset($tr[$var])) {

                    $value = $this->_get_var_value($var);

                    if (is_array($value) || is_object($value)) {
                        if ($value) {
                            $tr[$var] = "'" . str_replace("'", "\\'", json_encode($value)) . "'";
                        } else {
                            $tr[$var] = '0';
                        }
                    }
                    elseif (is_int($value)) {
                        $tr[$var] = $value;
                    }
                    elseif (is_string($value)) {
                        $tr[$var] = "'" . str_replace("'", "\\'", $value) . "'";
                    }
                    elseif ($value) {
                        $tr[$var] = '1';
                    }
                    else {
                        $tr[$var] = '0';
                    }
                }
            }

            uksort($tr, function($a, $b) {
                if (strlen($a) > strlen($b)) return -1;
                if (strlen($a) < strlen($b)) return 1;
                return 0;
            });

            foreach ($tr as $from => $to) {
                $condition = str_replace($from, $to, $condition);
            }

            try {
                $rv = eval("return ($condition);");
            }
            catch (Exception $e) {

            }
        }
        return $rv;
    }


    protected function _get_var_value($var, $default = '') {

        $rv = $default;
        $link = &$this->_data;

        $modifiers = preg_split('/\s*\|\s*/', $var);
        $varname = array_shift($modifiers);

        if (preg_match('/(.*)\+(.*)$/uis', $varname, $m)) {

            $from = $m[0];
            $var1 = $m[1];
            $var2 = $m[2];

            $value1 = $this->_get_var_value($var1);
            $value2 = $this->_get_var_value($var2);

            $to = "'" . $value1 . $value2 . "'";

            $varname = str_replace($from, $to, $varname);
        }

        if (preg_match_all('/\((.*)\?(.*):(.*)\)/uis', $varname, $m)) {

            $tr = array();

            foreach ($m[0] as $i => $replacement) {

                if (!isset($tr[$replacement])) {

                    $condition = $m[1][$i];
                    $true_var = $m[2][$i];
                    $false_var = $m[3][$i];

                    if ($this->_get_if_value($condition)) {
                        $tr[$replacement] = $true_var;
                    } else {
                        $tr[$replacement] = $false_var;
                    }
                }
            }

            foreach ($tr as $from => $to) {

                $varname = str_replace($from, $to, $varname);
            }
        }

        $varname = trim($varname);

        if (substr($varname, 0, 1) == "'" && substr($varname, -1) == "'") {

            $rv = substr($varname, 1, -1);

        } else {

            $parts = explode('.', $varname);

            while (count($parts)) {

                $part = array_shift($parts);

                if ($part == 'length' && is_array($link) && !$parts) {
                    return count($link);
                }

                if (strtolower(substr($part, 0, 7)) == 'indexof') {
                    if (is_array($link)) {
                        array_unshift($parts, $part);
                        $search_value = $this->_get_var_value(substr(join('.', $parts),8, -1));
                        $rv = array_search($search_value, $link);
                        if ($rv === false) $rv = -1;
                    } else {
                        $rv = -1;
                    }
                    return $rv;
                }

                if (isset($link[$part])) {
                    $link = &$link[$part];
                }
                else {
                    $part = strtolower($part);
                    if (isset($link[$part])) {
                        $link = &$link[$part];
                    } else {
                        return $rv;
                    }
                }
            }

            $rv = $link;
        }

        // TODO: apply modifiers

        return $rv;
    }


    protected function _get_for_block($for_condition, $html) {

        $rv = '';

        if (!$html) return $rv;

        $index_item_name = null;

        // TODO: Additional ngFor elements parsing needed

        $for_conditions = explode(';', $for_condition);

        $for_condition = trim(array_shift($for_conditions));

        foreach ($for_conditions as $condition) {

            if (preg_match('/[\s]+let[\s]+([^\s]+)[\s]+=[\s]+index/uis', $condition, $m)) {

                $index_item_name = $m[1];
            }
        }

        if (preg_match('/[\s]+([^\s]+)[\s]+of[\s]+([^\s]+)/uis', $for_condition, $m)) {

            $item_name = $m[1];
            $items_var = $m[2];

            $items = $this->_get_var_value($items_var);

            if ($items) {

                array_push($this->_data_stack, $this->_data);

                $data = $this->_data;

                $index = 0;

                foreach ($items as $item) {

                    $data[$item_name] = $item;

                    if ($index_item_name !== null) {
                        $data[$index_item_name] = $index;
                    }

                    $rv .= $this->render_html($html, $data);

                    $index++;
                }

                $this->_data = array_pop($this->_data_stack);
            }
        }

        if (!$rv) {
            $rv = '<!-- empty items -->';
        }

        return $rv;
    }
}