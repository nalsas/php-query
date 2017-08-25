<?php

abstract class ArrayQuery{
    abstract public function &get ($path = '', $default = null);
    abstract public function set ($value, $path = null);
    abstract function getSeparator ();

    public static function createWrapper($data){
        if(is_null($data)) $data =[];
        if(!is_array($data)) $data=[$data];
        return new static($data);
    }

    public function &find($query, &$paths=[]){
        $conditions = [];
        $queries = explode(',', $query);
        foreach($queries as $qry){
            $func =$this->parse(trim($qry));
            $conditions[]=$func;
        }
        if(empty($conditions)) $conditions[]=function($key, $value){return true;};
        $data = $this->get();
        $result = $this->_find($data, $conditions,$this->getSeparator(),$paths);
        $ret =static::createWrapper($result);
        $ret->setParent($this);
        return $ret;
    }

    private function parse($query){
        $ops = ['>=', '==', '!=', '<=', '<', '>'];
        $func = [];
        $tokens = preg_split("/(\>\=|\<\=|\=\=|\>|\<|\!\=)/", $query, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $comparator = '==';
        $destKey = null;

        if(empty($tokens)) return $func;
        $dest = array_pop($tokens);
        $curToken = array_pop($tokens);

        if(in_array($curToken, $ops)) {
            $comparator =trim($curToken);
        }

        $destKey = array_pop($tokens);
        switch($comparator){
            case '>=':
                $compfunc = function($v1, $v2){ return $v1>=$v2;};
                break;
            case '<=':
                $compfunc = function($v1, $v2){ return $v1<=$v2;};
                break;
            case '==':
                if($this->_searchOptions['compareMode']=='strict')
                    $compfunc = function($v1, $v2){ return $v1==$v2;};
                else
                    $compfunc = function($v1, $v2){ return strpos($v1, $v2)!==false;};

                break;
            case '!=':
                $compfunc = function($v1, $v2){ return $v1!=$v2;};
                break;
            case '>':
                $compfunc = function($v1, $v2){ return $v1>$v2;};
                break;
            case '<':
                $compfunc = function($v1, $v2){ return $v1<$v2;};
                break;
        }

        $dest = explode('|', $dest);
        $destKey = is_null($destKey)?$destKey:trim($destKey);

        foreach($dest as $destValue){
            $destValue= trim($destValue)==='null'? null:trim($destValue);
            $func[]=$this->generateFunc($compfunc, $destKey, $destValue);
        }
        return function($key, $value)use($func){
            foreach($func as $f){
                if($f($key, $value)) return true;
            }
            return false;
        };
    }

    private function generateFunc($compfunc, $destKey, $dest){

        $func = function($key, $value) use($compfunc, $destKey, $dest) {

            if('strict'==$this->_searchOptions['compareMode']){
                //In strict mode, if dest is numeric, we only match the numeric
                if(is_numeric($dest) && (!is_numeric($value)))
                    return false;
            }

            if(!is_null($destKey)){
                return ($destKey.''===$key.'') && ($dest==='*'?true:$compfunc($value, $dest));
            }else{
                return $dest==='*'?true:$compfunc($value, $dest);
            }

            return true;
        };
        return $func;
    }

    //Recursive could be optimized by higher order function?
    private function &_find(array &$data, array $conditions, $sep, &$path=[], $curPath=''){
        $result = [];
        foreach($data as $key=>&$value){
           if(is_array($value)){
              //$path[$key]=$curPath.$sep.$key;
              $result = array_merge($result, $this->_find($value, $conditions, $sep,  $path, $curPath.$sep.$key));
           }else{
               $match=array_map(function($func) use($key, $value){
                   return $func($key, $value);
               }, $conditions);

               $isMatched = function() use($match){
                   if($this->_searchOptions['matchMode']=='or'){
                       return in_array(true, $match);
                   }else{
                       return !in_array(false, $match);
                   }
               };

               if($isMatched()){
                   $result[] =$this->_searchOptions['resultMode']=='value'?$value:$data;
                   $path[$key][]=$curPath.$sep.$key;
               }
           }
        }

        return $result;
    }

    /**
     * Update the data collection with given key=>value pairs
     *
     * There are 2 kinds of usage of this function:
     * 1. without force() option, this cause we only replace the corresponding value of specific key
     * 2. with force(), we will insert the given key and data recursively into the data which this object holds
     * @param array $values
     * @return $this
     * @throws Exception
     */
    public function update(array $values){
        if($this->_searchOptions['forceUpdateMode']){
            $paths=[];
            $this->find('*', $paths);
            $paths = static::createWrapper($paths)->valueMode()->find('*')->get();
            $sep = $this->getSeparator();

            foreach($paths as $path){
                array_map(function($k, $v) use ($path, $sep){
                    $higherPath =static::getHigherLevelPath($path, $sep);
                    $calculated=is_callable($v)?$v($this->get($higherPath)):$v;
                    $this->set($calculated, $higherPath.$sep.$k);
                }, array_keys($values), $values);
            }

            return $this;
        }else //set the exist key
            return $this->replace($values);
    }


    public function replace(array $values){
        $sep = $this->getSeparator();
        $conditions = [];
        $paths =[];
        foreach($values as $key=>$value){
            $func = function($k, $v) use ($key){
                return $k===$key;
            };
            $conditions[]=$func;
        }

        if(empty($conditions)) throw new Exception('empty input is not allowed!');
        $data = $this->get();

        $matchMode = $this->_searchOptions['matchMode'];
        $this->_searchOptions['matchMode'] = 'or';
        $this->_find($data, $conditions, $this->getSeparator(), $paths);
        $this->_searchOptions['matchMode'] = $matchMode;

        //set the exist key
        array_map(function($k, $v) use ($paths, $sep){
            if(!isset($paths[$k])) return;
            foreach($paths[$k] as $path){
                $calculated=is_callable($v)?$v($this->get(static::getHigherLevelPath($path, $sep))):$v;
                $this->set($calculated, $path);
            }
        }, array_keys($values), $values);

        return $this;
    }
    
    public function renameKey(array $keyMap){
        $sep = $this->getSeparator();
        $conditions = [];
        foreach($keyMap as $key=>$value){
            $func = function($k, $v) use ($key){
                return $k===$key;
            };
            $conditions[]=$func;
        }

        if(empty($conditions)) throw new Exception('empty input is not allowed!');

        array_map(function($oldkey, $newkey) use ($sep){
            $paths = $this->findPathsByKey([$oldkey]);
            foreach($paths as $path){
                $higherLevelPath = static::getHigherLevelPath($path, $sep);
                $parent=$this->get($higherLevelPath);
                $parent[$newkey]=$parent[$oldkey];
                unset($parent[$oldkey]);
                $this->set($parent, $higherLevelPath);
            }
        }, array_keys($keyMap), $keyMap);

        return $this;
    }
    
    public function map(callable $operation){
        $this->set(array_map($operation,$this->get()));
        return $this;
    }
    
    public function filter(callable $operation){
        $this->set(array_filter($this->get(), $operation));
        return $this;
    }
    
    public function mapRoot(callable $operation){
        $separator =$this->getSeparator();
        $obj = $this;
        while($obj->getParent()!=NULL) $obj = $obj->getParent();

        $paths=static::createWrapper($this->_limitPathResult)->valueMode()->find('*')->get();
        array_map(function($path) use($obj, $separator, $operation){
            $rowPath =$this->_searchOptions['resultMode']==='value'?$path:static::getHigherLevelPath($path, $separator);
            $row =$obj->get($rowPath);
            if(is_array($rowPath)) {var_dump($rowPath);die();}
            $obj->set($operation($row), $rowPath);
        }, $paths);

        //let limit result take effect in root object
        $obj->_limitPathResult = $this->_limitPathResult;
        $obj->_searchOptions = $this->_searchOptions;
        return $obj;
    }

    public function updateRoot(array $values){
        $separator =$this->getSeparator();
        $obj = $this;
        while($obj->getParent()!=NULL) $obj = $obj->getParent();

        $paths=static::createWrapper($this->_limitPathResult)->valueMode()->find('*')->get();
        array_map(function($path) use($values, $obj, $separator){
            $rowPath = static::getHigherLevelPath($path, $separator);
            $row =$obj->get($rowPath);
            foreach($values as $key=>$value){
                //get row
                $calculated=is_callable($value)?$value($row):$value;
                if(isset($row[$key]) || $this->_searchOptions['forceUpdateMode'])
                    $obj->set($calculated, $rowPath.$separator.$key);
            }
        }, $paths);

        //let limit result take effect in root object
        $obj->_limitPathResult = $this->_limitPathResult;
        $obj->_searchOptions = $this->_searchOptions;
        return $obj;
    }

    public function deleteRoot(array $keys=null){
        $obj = $this;
        while($obj->getParent()!=NULL) $obj = $obj->getParent();

        $sep = $this->getSeparator();
        $paths=static::createWrapper($this->_limitPathResult)->valueMode()->find('*')->get();
        array_map(function($path) use($keys, $obj, $sep){
            if(!isset($keys)){
                $rowPath = static::getHigherLevelPath($path, $sep);
                list($parentPath, $rowKey) = static::getHigherLevelPath($rowPath, $sep, true);
                if($rowPath!=$parentPath){
                    $rowParent =$obj->get($parentPath);
                    unset($rowParent[$rowKey]);
                    $obj->set($rowParent, $parentPath);
                }
            }
            else{
                $rowPath = static::getHigherLevelPath($path, $sep);
                $row =$obj->get($rowPath);
                foreach($keys as $key){
                    unset($row[$key]);
                }
                $obj->set($row, $rowPath==''?null:$rowPath);
            }
        }, $paths);
        return $obj;
    }
    
    //remove all data except the data with specific keys
    //**NOTE**: this function only handle the leaf node in multi-dimension array!
    public function reserve(array $keys){
        $data = $this->get();
        $allkeys=[];
        array_walk_recursive($data, function($value, $key)use(&$allkeys){ $allkeys[]=$key; });
        $keys=array_diff($allkeys, $keys);
        return $this->delete($keys);
    }

    public function delete(array $keys){
        $sep = $this->getSeparator();
        $paths = $this->findPathsByKey($keys);
        array_map(function($path) use ($keys, $sep){
            list($higherPath, $current)=static::getHigherLevelPath($path, $sep, true);
            $row =$this->get($higherPath);
            unset($row[$current]);

            if($higherPath=='')
                $this->set($row);
            else
                $this->set($row, $higherPath);
        }, $paths);

        return $this;
    }

    public function force(){
        $this->_searchOptions=array_merge($this->_searchOptions, ['forceUpdateMode'=>true]);
        return $this;
    }

    public function getPaths(){
        return $this->_limitPathResult;
    }

    //Maybe should be moved to ArrayAsPath?
    public function erase ($path = null) {
        if (!isset($path)) {
            return $this;
        }
        $sep=$this->getSeparator();

        list($path, $last) = static::getHigherLevelPath($path, $sep, true);
        $curData=$this->get($path);
        unset($curData[$last]);
        if($path=='')
            $this->set($curData);
        else
            $this->set($curData, $path);
            
        return $this;
    }

    public function replaceByKey(array $kv){
        $keys = array_keys($kv);
        $sep = $this->getSeparator();
        $paths = $this->findPathsByKey($keys);
        array_map(function($path) use ($kv, $sep){
            list($higherPath, $current)=static::getHigherLevelPath($path, $sep, true);
            if(isset($kv[$current])){
                $dest =$kv[$current];
                $row = $this->get($higherPath);
                if(is_array($row)&&isset($row[$current])){
                    $old=$row[$current];
                    $dest= is_callable($dest)? $dest($old):$dest;
                    $this->set($dest, $path);
                }
            }
        }, $paths);

        return $this;
    }

    public function findPathsByKey(array $keys){
        return $this->_findPathsByKey($keys, $this->get() ,$this->getSeparator());
    }

    public function _findPathsByKey(array $keys, array $array, $sep, $curPath='') {
        $ret=[];
        foreach ($array as $key => $v) {
            $newCurPath=$curPath.$sep.$key;
            if(in_array($key, $keys, true)){
                $ret[]=$newCurPath;
            }
            if (is_array($v)) {
                $ret=array_merge($ret, $this->_findPathsByKey($keys, $v, $sep, $newCurPath));
            }
        }
        return $ret;
    }

    public static function getHigherLevelPath($path, $sep, $returnAll=false){
        $paths=explode($sep, $path);
        $last=array_pop($paths);
        return $returnAll?[implode($sep,$paths), $last]:implode($sep,$paths);
    }

    /**
     * Only take effect when followed by updateRoot/deleteRoot/getPaths function
     * @param $query
     * @return mixed
     */
    public function limit($query){
        $paths = [];

        $results=$this->find($query, $paths);

        //make result mode in $results align with parent
        $results->setOptions($this->_searchOptions);

        return $results->setLimitPathResult($paths);
    }

    /**
     * Only take effect after limit() called!
     * @param $criteria
     * @return ArrayQuery
     */
    public function orderBy($criteria){
        if(is_callable($criteria)){
            $result=$this->getLimitPathResult();
            $sep = $this->getSeparator();
            $obj = $this;
            while($obj->getParent()!=NULL) $obj = $obj->getParent();
            foreach ($result as $k=>&$paths){
                usort($paths, function($path1, $path2)use($criteria, $sep, $obj){
                    $path1=static::getHigherLevelPath($path1, $sep);
                    $path2=static::getHigherLevelPath($path2, $sep);
                    return $criteria($obj->get($path1), $obj->get($path2));
                });
            }
            $this->setLimitPathResult($result);
        }
        return $this;    
    }

    /**
     * Only take effect after limit() called!
     * @param $query
     * @return ArrayQuery
     */
    public function exclude($query){
        $sep = $this->getSeparator();
        $paths = [];
        $this->findRoot()->find($query, $paths);
        $paths = static::createWrapper($paths)->valueMode()->find('*')->get();
        $paths = array_map(function($v) use($sep){ return static::getHigherLevelPath($v, $sep);}, $paths);

        $finalPaths=$this->getPaths();
        //make result mode in $results align with parent
        foreach($finalPaths as $key=>&$destPaths){
            $newPaths=array_filter($destPaths, function($path) use($paths, $sep){
                return !in_array(static::getHigherLevelPath($path,$sep), $paths);
            });
            $finalPaths[$key] = $newPaths;
        }
        return $this->setLimitPathResult($finalPaths);
    }

    public function findRoot(){
        $obj=$this;
        while($obj->getParent()!=NULL) $obj = $obj->getParent();
        return $obj;
    }

    public function setLimitPathResult($paths){
        $this->_limitPathResult = $paths;
        return $this;
    }
    
    protected function getLimitPathResult(){
        return $this->_limitPathResult;
    }

    public function &setOptions(array $options){
        $this->_searchOptions=array_merge($this->_searchOptions, $options);
        return $this;
    }

    // syntax surgar
    public function &valueMode(){
        $this->_searchOptions=array_merge($this->_searchOptions, ['resultMode'=>'value']);
        return $this;
    }

    // syntax surgar
    public function &rowMode(){
        $this->_searchOptions=array_merge($this->_searchOptions, ['resultMode'=>'value']);
        return $this;
    }

    public function &setParent(&$obj){
        $this->_parent = $obj;
        return $this;
    }

    // syntax surgar
    public function &fuzzyMode(){
        $this->_searchOptions=array_merge($this->_searchOptions, ['compareMode'=>'fuzzy']);
        return $this;
    }

    public function &getParent(){
        return $this->_parent;
    }

    public function &resetParent(){
       $this->_parent=null;
       return $this;
    }
   
    // 'class' in $config means the plugin's class name
    // later in getPluginInst, we will pass the class instance to the constructor of plugin's class to instantiate
    // plugin when needed
    public static function addPlugin(array $config){
        self::$_plugins[@$config['name']]=['class'=>$config['class']];
    }
    
    public function getPluginInst($name){
        return new self::$_plugins[$name]['class']($this);
    }

    private $_searchOptions=[
        'matchMode'=>'and',
        'compareMode'=>'strict',
        'resultMode'=>'row',
        'forceUpdateMode'=>false
    ];

    private $_limitPathResult=null;
    private $_parent=null;
    private static $_plugins=[];
}