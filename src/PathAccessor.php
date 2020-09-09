<?php
namespace PHPQuery;

interface PathAccessor {
     public function &get ($path = '', $default = null);
     public function set ($value, $path = null);
     public function getSeparator ();
}
