<?php
namespace PHPQuery;

class ArrayQuery extends Querier{

    protected
        $data = [],
        $separator = '/';

    public function __construct (array $data = []) {
        $this->data = $data;
    }

    public function set ($value, $path = null) {
        if (empty($path)) {
            $this->data = $value;
        }

        $separator = $this->separator;
        $pathToken = strtok($path, $separator);

        $code = '';
        $pices = '[\''.$pathToken.'\']';
        while ($pathToken !== false) {
            if (($pathToken = strtok($separator)) !== false) {
                $code .= 'if (!isset($this->data'.$pices.')) $this->data'.$pices.' = array(); ';
                $pices .= '[\''.$pathToken.'\']';
            } else {
                $code .= 'return $this->data'.$pices.' = $value;';
            }
        }
        $ret=eval($code);
        return $ret?$this:$ret;
    }

    public function &get ($path = '', $default = []) {
        $result = &$this->data;
        $separator = $this->separator;
        $pathToken = strtok($path, $separator);

        while ($pathToken !== false) {
            if (!isset($result[$pathToken]) || is_string($result)) {
                if (isset($default)) {
                    return $default;
                }

                throw new QueryException ('Can\'t find "'.$pathToken.'" in "'.$path.'"');
            }

            $result = &$result[$pathToken];
            $pathToken = strtok($separator);
        }
        $result =$result ? $result : $default;
        return $result;
    }

    public function has ($path) {
        $result = $this->data;
        $separator = $this->separator;
        $pathToken = strtok($path, $separator);

        while ($pathToken !== false) {
            if (!isset($result[$pathToken]) || is_string($result)) {
                return false;
            }

            $result = $result[$pathToken];
            $pathToken = strtok($separator);
        }

        return true;
    }

    public function setSepatator ($separator) {
        $this->separator = $separator;
    }

    public function getSeparator () {
        return $this->separator;
    }

}