<?php
namespace PHPQuery;

abstract class Querier implements PathAccessor {

    //Deprecated, use wrap instead!!
    public static function createWrapper($data){
        return static::wrap($data);
    }

    public static function wrap($data){
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
        $ret =static::wrap($result);
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
                if(substr($destKey,-1,1)=='*'){
                    //do not use regex now for performance considering
                    return (strpos($key.'', rtrim($destKey,'*'))===0) && ($dest==='*'?true:$compfunc($value, $dest));
                }else
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

    public function replace(array $values){
        $data = $this->get();
        array_walk_recursive($data, function (&$v)use($values){
            if(isset($values[$v])){
                $v = is_callable($values[$v])?$values[$v]($v):$values[$v];
            }
        });
        $this->set($data);
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

        if($this->getWhenResultPath()==null){
            throw new QueryException('please call when() first!');
        }

        $separator =$this->getSeparator();
        $obj = $this->getRoot();
        $paths=static::wrap($this->getWhenResultPath())->valueMode()->find('*')->get();
        array_map(function($path) use($obj, $separator, $operation){
            $rowPath =$this->_searchOptions['resultMode']==='value'?$path:static::getHigherLevelPath($path, $separator);
            $row =$obj->get($rowPath);
            $obj->set($operation($row), $rowPath);
        }, $paths);

        //let when() result take effect in root object
        $obj->setWhenResultPath($this->getWhenResultPath());
        $obj->setOptions($this->_searchOptions, true);
        return $obj;
    }

    public function deleteRoot(array $keys=null){
        if($this->getWhenResultPath()==null){
            throw new QueryException('please call when() first!');
        }

        $obj = $this->getRoot();
        $sep = $this->getSeparator();
        $paths=static::wrap($this->getWhenResultPath())->valueMode()->find('*')->get();
        array_map(function($path) use($keys, $obj, $sep){
            if(!isset($keys)){
                $obj->erase($path);
            }else{
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

    /**
     * Only take effect after when() called!
     * @param $criteria
     * @return Querier
     */
    public function orderBy($criteria){

        if(is_callable($criteria)){
            $result=$this->getWhenResultPath();
            $sep = $this->getSeparator();
            $obj = $this->getRoot();
            foreach ($result as $k=>&$paths){
                usort($paths, function($path1, $path2)use($criteria, $sep, $obj){
                    $path1=static::getHigherLevelPath($path1, $sep);
                    $path2=static::getHigherLevelPath($path2, $sep);
                    return $criteria($obj->get($path1), $obj->get($path2));
                });
            }
            $this->setWhenResultPath($result);
        }
        return $this;
    }

    /**
     * Only take effects after when() called!
     * @param $query
     * @return Querier
     */
    public function exclude($query){

        if($this->getWhenResultPath()==null){
            throw new QueryException('please call when() first!');
        }

        $sep = $this->getSeparator();
        $paths = [];
        $this->getRoot()->find($query, $paths);
        $paths = static::wrap($paths)->valueMode()->find('*')->get();
        $paths = array_map(function($v) use($sep){ return static::getHigherLevelPath($v, $sep);}, $paths);

        $finalPaths=$this->getWhenResultPath();
        //make result mode in $results align with parent
        foreach($finalPaths as $key=>&$destPaths){
            $newPaths=array_filter($destPaths, function($path) use($paths, $sep){
                return !in_array(static::getHigherLevelPath($path,$sep), $paths);
            });
            $finalPaths[$key] = $newPaths;
        }
        return $this->setWhenResultPath($finalPaths);
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

    public function getPaths(){
        return static::wrap($this->getWhenResultPath())->valueMode()->find('*')->get();
    }

    //Maybe should be moved to ArrayQuery?
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

    //TODO: Add support for when()
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
     * Only take effect when followed by mapRoot/deleteRoot/exclude/getPaths function
     * after we use when(), all paths are relative to root
     * @param $query
     * @return mixed
     */
    public function when($query){
        $paths = [];

        if(isset($this->_parent)){
            throw new QueryException('Currently, when() could only be used on root!');
        }

        $this->find($query, $paths);

        //make result mode in $results align with parent
        $this->setOptions($this->_searchOptions);
        $this->setWhenResultPath($paths);
        return $this;
    }

    /**
     *  Clean the inner array of result array
     */
    public function clean($white_list=[]){
        $obj=$this->get();
        $obj=array_map(function($row)use($white_list){
            foreach ($row as $k=>$field){
                if(is_array($field)&&!in_array($k, $white_list)){
                    unset($row[$k]);
                }
            }
            return $row;
        }, $obj);
        $this->set($obj);
        return $this;
    }

    public function getRoot(){
        $obj=$this;
        while($obj->getParent()!=NULL) $obj = $obj->getParent();
        return $obj;
    }

    public function setWhenResultPath($paths){
        $this->_whenResultPath = $paths;
        return $this;
    }
    
    protected function getWhenResultPath(){
        return $this->_whenResultPath;
    }

    public function &setOptions(array $options, $overide=false){
        $this->_searchOptions=$overide?$options:array_merge($this->_searchOptions, $options);
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

    public function &setRoot(){
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
    ];

    private $_whenResultPath=null;
    private $_parent=null;
    private static $_plugins=[];
}