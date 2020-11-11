<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/PQTestCase.php');

use PHPQuery\ArrayQuery;

class ArrayQueryTest extends PQTestCase
{

    public function testFind(){
        $testArr = [
            'hello' => 'the world',
            'foo' =>[
                'apple' => 1,
                2 => 'good',
                'pear' => 'big',
                'good' => 5

            ],
            'count'=>9,
            'apple'=>10,
            20
        ];

        $testArr2 = [
            'hello' => 'the world',
            'foo' =>[
                'apple' => ['taste'=>'sweet'],
                2 => 'good',
                'pear' => 'big',
                'good' => 5,
                'foo' => 10,
            ],
            'count'=>9,
            'apple'=>10,
            20
        ];

        $this->assertEquals('[{"apple":1,"2":"good","pear":"big","good":5}]',json_encode(ArrayQuery::wrap($testArr)->find('1')->get()));
        $this->assertEquals('[{"apple":1,"2":"good","pear":"big","good":5},{"hello":"the world","foo":{"apple":1,"2":"good","pear":"big","good":5},"count":9,"apple":10,"0":20}]',json_encode(ArrayQuery::wrap($testArr)->find('apple==*')->get()));
        $this->assertEquals('[{"apple":1,"2":"good","pear":"big","good":5}]', json_encode(ArrayQuery::wrap($testArr)->find('>=2, <6')->get()));

        //Doesn't support such kind of usage!
        //echo json_encode(ArrayQuery::wrap($testArr)->find('apple>0, good<6')->get());

        $this->assertEquals('[{"apple":1,"2":"good","pear":"big","good":5}]', json_encode(ArrayQuery::wrap($testArr)->find('apple==good|1')->get()));
        $this->assertEquals('[{"apple":1,"2":"good","pear":"big","good":5}]', json_encode(ArrayQuery::wrap($testArr)->find('2==1|good')->get()));

        $this->assertEquals('[1]', json_encode(ArrayQuery::wrap($testArr)->setOptions(['resultMode'=>'value'])->find('1')->get()));
        $this->assertEquals('[1,10]',json_encode(ArrayQuery::wrap($testArr)->setOptions(['resultMode'=>'value'])->find('apple==*')->get()));
        $this->assertEquals('[5]',json_encode(ArrayQuery::wrap($testArr)->setOptions(['resultMode'=>'value'])->find('>=2, <6')->get()));
        $this->assertEquals('[1]', json_encode(ArrayQuery::wrap($testArr)->setOptions(['resultMode'=>'value'])->find('apple==good|1')->get()));
        $this->assertEquals('["good"]',json_encode(ArrayQuery::wrap($testArr)->setOptions(['resultMode'=>'value'])->find('2==1|good')->get()));

        $obj=ArrayQuery::wrap($testArr)->when('appletree==*')->mapRoot(function($v){return $v;})->find('appletree==*');
        $this->assertEquals('[]', json_encode($obj->get()));

        $obj=ArrayQuery::wrap($testArr)->when('apple==*')->deleteRoot(['apple']);
        $this->assertEquals('{"hello":"the world","foo":{"2":"good","pear":"big","good":5},"count":9,"0":20}', json_encode($obj->get()));

        $paths=ArrayQuery::wrap($testArr2)->findPathsByKey(['foo']);
        $this->assertEquals('["\/foo","\/foo\/foo"]', json_encode($paths));

        $ret=ArrayQuery::wrap($testArr2)->replaceByKey(['foo'=>1]);
        $this->assertEquals('{"hello":"the world","foo":1,"count":9,"apple":10,"0":20}', json_encode($ret->get()));

        $paths=ArrayQuery::wrap($testArr2)->findPathsByKey(['apple']);
        $this->assertEquals('["\/foo\/apple","\/apple"]', json_encode($paths));

        $ret=ArrayQuery::wrap($testArr2)->replaceByKey(['apple'=>function($v){
            if(is_array($v))
                $v['look']='good';
            else
                $v=$v+2;
            return $v;
        }]);
        $this->assertEquals('{"hello":"the world","foo":{"apple":{"taste":"sweet","look":"good"},"2":"good","pear":"big","good":5,"foo":10},"count":9,"apple":12,"0":20}',
            json_encode($ret->get()));

        $ret=ArrayQuery::wrap($testArr2)->find('good==*')->replaceByKey(['apple'=>'replaced']);
        $this->assertEquals('[{"apple":"replaced","2":"good","pear":"big","good":5,"foo":10}]', json_encode($ret->get()));

        // TODO: support such usage
        //$ret=ArrayQuery::wrap($testArr2)->when('good==*')->replaceByKey(['apple'=>'replaced']);
        //echo json_encode($ret->getRoot()->get());

        $testArr3 = [
            'hello' => null,
            'foo' =>[
                'apple' => ['taste'=>'sweet'],
                2 => 'good',
                'pear' => 'big',
                'good' => null,
                'foo' => 10,
                'hello' => 1,
            ],
            'count'=>9,
            'apple'=>10,
            20
        ];
        $ret=ArrayQuery::wrap($testArr3)->find('hello!=null');
        $this->assertEquals('[{"apple":{"taste":"sweet"},"2":"good","pear":"big","good":null,"foo":10,"hello":1}]', json_encode($ret->get()));
        $ret=ArrayQuery::wrap($testArr3)->valueMode()->find('hello!=null');
        $this->assertEquals('[1]', json_encode($ret->get()));

        $ret=ArrayQuery::wrap($testArr)->delete(['apple']);
        $this->assertEquals('{"hello":"the world","foo":{"2":"good","pear":"big","good":5},"count":9,"0":20}',
            json_encode($ret->get()));

        $ret=ArrayQuery::wrap($testArr)->reserve(['apple']);
        $this->assertEquals('{"foo":{"apple":1},"apple":10}', json_encode($ret->get()));

        $testArr4 = [
            'obj_32323' => null,
            'foo' =>[
                'obj_1231' => ['taste'=>'sweet'],
                2 => 'good',
                'pear' => 'big',
                'good' => null,
                'foo' => 10,
                'obj_5555' => [
                    'obj_67777'=>10,
                ],
            ],
            'count'=>9,
            'apple'=>10,
            20
        ];

        $path=[];

        //Only leaf node will be return
        $ret=ArrayQuery::wrap($testArr4)->find('obj_*==*', $path);
        $this->assertEquals('[{"obj_32323":null,"foo":{"obj_1231":{"taste":"sweet"},"2":"good","pear":"big","good":null,"foo":10,"obj_5555":{"obj_67777":10}},"count":9,"apple":10,"0":20},{"obj_67777":10}]',
            json_encode($ret->get()));

        //echo microtime(true) - $t;
        //$data=xhprof_disable();
        //print_r($data);


    }

//    public function testFindBigArray(){
//        $data =json_decode($this->testData1, true);
//        $this->assertEquals(count(ArrayAsPath::wrap($data)->find('parameter_id == *')->get()),26);
//        $this->assertEquals(count(ArrayAsPath::wrap($data)->find('display_name == *')->get()),34);
//        $this->assertEquals(count(ArrayAsPath::wrap($data)->setOptions(['compareMode'=>'fuzzy', 'resultMode'=>'value'])
//            ->find('display_name == 系统')->get()),9);
//
//        //$t = microtime(true);
//        $obj=ArrayAsPath::wrap($data)->setOptions(['compareMode'=>'fuzzy'])->find('display_name==系统');
//        print_r($obj->get());
//        //echo microtime(true) - $t;
//        //xhprof_enable(XHPROF_FLAGS_CPU);
//
//    }

    public function saveProfiling($data)
    {
        //
        // Saving the XHProf run
        // using the default implementation of iXHProfRuns.
        //
        include_once "xhprof_lib/utils/xhprof_lib.php";
        include_once "xhprof_lib/utils/xhprof_runs.php";

        $xhprof_runs = new XHProfRuns_Default();

        // Save the run under a namespace "xhprof_foo".
        //
        // **NOTE**:
        // By default save_run() will automatically generate a unique
        // run id for you. [You can override that behavior by passing
        // a run id (optional arg) to the save_run() method instead.]
        //
        $run_id = $xhprof_runs->save_run($data, "xhprof_foo");

        echo "---------------\n".
            "Assuming you have set up the http based UI for \n".
            "XHProf at some address, you can view run at \n".
            "http://<xhprof-ui-address>/index.php?run=$run_id&source=xhprof_foo\n".
            "---------------\n";
    }
}
