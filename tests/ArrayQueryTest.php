<?php
//可重复运行
//require_once(__DIR__ . '/../src/ArrayQuery.php');
//require_once(__DIR__ . '/../src/ArrayAsPath.php');
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/PQTestCase.php');

use PHPQuery\ArrayAsPath;

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

        $this->assertEquals('[{"apple":1,"2":"good","pear":"big","good":5}]',json_encode(ArrayAsPath::wrap($testArr)->find('1')->get()));
        $this->assertEquals('[{"apple":1,"2":"good","pear":"big","good":5},{"hello":"the world","foo":{"apple":1,"2":"good","pear":"big","good":5},"count":9,"apple":10,"0":20}]',json_encode(ArrayAsPath::createWrapper($testArr)->find('apple==*')->get()));
        $this->assertEquals('[{"apple":1,"2":"good","pear":"big","good":5}]', json_encode(ArrayAsPath::wrap($testArr)->find('>=2, <6')->get()));

        //$this->assertEquals('[{"apple":1,"2":"good","pear":"big","good":5}]', json_encode(ArrayAsPath::createWrapper($testArr)->find('apple>0, good<6')->get()));
        //暂时不支持这种用法！！！
        echo json_encode(ArrayAsPath::wrap($testArr)->find('apple>0, good<6')->get());

        $this->assertEquals('[{"apple":1,"2":"good","pear":"big","good":5}]', json_encode(ArrayAsPath::wrap($testArr)->find('apple==good|1')->get()));
        $this->assertEquals('[{"apple":1,"2":"good","pear":"big","good":5}]', json_encode(ArrayAsPath::wrap($testArr)->find('2==1|good')->get()));

        $this->assertEquals('[1]', json_encode(ArrayAsPath::wrap($testArr)->setOptions(['resultMode'=>'value'])->find('1')->get()));
        $this->assertEquals('[1,10]',json_encode(ArrayAsPath::wrap($testArr)->setOptions(['resultMode'=>'value'])->find('apple==*')->get()));
        $this->assertEquals('[5]',json_encode(ArrayAsPath::wrap($testArr)->setOptions(['resultMode'=>'value'])->find('>=2, <6')->get()));
        $this->assertEquals('[1]', json_encode(ArrayAsPath::wrap($testArr)->setOptions(['resultMode'=>'value'])->find('apple==good|1')->get()));
        $this->assertEquals('["good"]',json_encode(ArrayAsPath::wrap($testArr)->setOptions(['resultMode'=>'value'])->find('2==1|good')->get()));

        $obj=ArrayAsPath::wrap($testArr)->limit('apple==*')->force()
            ->updateRoot(['shit'=>1])
            ->updateRoot(['fuck'=>function($row){return $row['apple'];}]);
        $this->assertEquals('{"hello":"the world","foo":{"apple":1,"2":"good","pear":"big","good":5,"shit":1,"fuck":1},"count":9,"apple":10,"0":20,"shit":1,"fuck":10}',
            json_encode($obj->get()));

        $obj=ArrayAsPath::wrap($testArr)->limit('apple==*')->deleteRoot(['apple']);
        $this->assertEquals('{"hello":"the world","foo":{"2":"good","pear":"big","good":5},"count":9,"0":20}', json_encode($obj->get()));

        $obj=ArrayAsPath::wrap($testArr)->limit('apple==*')->exclude('count==*')->updateRoot(['apple'=>'xxx']);
        $this->assertEquals('{"hello":"the world","foo":{"apple":"xxx","2":"good","pear":"big","good":5},"count":9,"apple":10,"0":20}',json_encode($obj->get()) );

        //TODO: 需要修复此case, 目前find后无法接limit系列
        //$obj=ArrayAsPath::wrap($testArr)->find('apple==1');
        //$obj->limit('apple==*')->force()->updateRoot(['shit'=>'xxx']);
        //print_r($obj->get());

        $paths=ArrayAsPath::wrap($testArr2)->findPathsByKey(['foo']);
        $this->assertEquals('["\/foo","\/foo\/foo"]', json_encode($paths));

        $ret=ArrayAsPath::wrap($testArr2)->replaceByKey(['foo'=>1]);
        $this->assertEquals('{"hello":"the world","foo":1,"count":9,"apple":10,"0":20}', json_encode($ret->get()));

        $paths=ArrayAsPath::wrap($testArr2)->findPathsByKey(['apple']);
        $this->assertEquals('["\/foo\/apple","\/apple"]', json_encode($paths));

        $ret=ArrayAsPath::wrap($testArr2)->replaceByKey(['apple'=>function($v){
            if(is_array($v))
                $v['look']='good';
            else
                $v=$v+2;
            return $v;
        }]);
        $this->assertEquals('{"hello":"the world","foo":{"apple":{"taste":"sweet","look":"good"},"2":"good","pear":"big","good":5,"foo":10},"count":9,"apple":12,"0":20}',
            json_encode($ret->get()));

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
        $ret=ArrayAsPath::wrap($testArr3)->find('hello!=null');
        $this->assertEquals('[{"apple":{"taste":"sweet"},"2":"good","pear":"big","good":null,"foo":10,"hello":1}]', json_encode($ret->get()));
        $ret=ArrayAsPath::wrap($testArr3)->valueMode()->find('hello!=null');
        $this->assertEquals('[1]', json_encode($ret->get()));

        $ret=ArrayAsPath::wrap($testArr)->find('apple == *')->update(['apple'=>0]);
        $this->assertEquals('[{"apple":0,"2":"good","pear":"big","good":5},{"hello":"the world","foo":{"apple":0,"2":"good","pear":"big","good":5},"count":9,"apple":0,"0":20}]',
            json_encode($ret->get()));

        $ret=ArrayAsPath::wrap($testArr)->find('apple == *')->force()->update(['xxx'=>1]);
        $this->assertEquals('[{"apple":1,"2":"good","pear":"big","good":5,"xxx":1},{"hello":"the world","foo":{"apple":1,"2":"good","pear":"big","good":5,"xxx":1},"count":9,"apple":10,"0":20,"xxx":1}]',
            json_encode($ret->get()));

        $ret=ArrayAsPath::wrap($testArr)->delete(['apple']);
        $this->assertEquals('{"hello":"the world","foo":{"2":"good","pear":"big","good":5},"count":9,"0":20}',
            json_encode($ret->get()));

        $ret=ArrayAsPath::wrap($testArr)->reserve(['apple']);
        echo json_encode($ret->get());

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
        $ret=ArrayAsPath::wrap($testArr4)->find('obj_*==*', $path);
        $this->assertEquals('[{"obj_32323":null,"foo":{"obj_1231":{"taste":"sweet"},"2":"good","pear":"big","good":null,"foo":10,"obj_5555":{"obj_67777":10}},"count":9,"apple":10,"0":20},{"obj_67777":10}]',
            json_encode($ret->get()));

        //echo microtime(true) - $t;
        //$data=xhprof_disable();
        //print_r($data);


    }

    public function testFindBigArray(){
        $data =json_decode($this->testData1, true);
        $this->assertEquals(count(ArrayAsPath::wrap($data)->find('parameter_id == *')->get()),26);
        $this->assertEquals(count(ArrayAsPath::wrap($data)->find('display_name == *')->get()),34);
        $this->assertEquals(count(ArrayAsPath::wrap($data)->setOptions(['compareMode'=>'fuzzy', 'resultMode'=>'value'])
            ->find('display_name == 系统')->get()),9);

        //$t = microtime(true);
        $obj=ArrayAsPath::wrap($data)->setOptions(['compareMode'=>'fuzzy'])->find('display_name==系统');
            //->update(['display_name'=>'good', 'monitor_idx'=>0]);
        print_r($obj->get());
        //echo microtime(true) - $t;
        //xhprof_enable(XHPROF_FLAGS_CPU);

//        $obj=ArrayAsPath::wrap($data)->setOptions(['compareMode'=>'fuzzy'])->limit('parameter_id>7')->updateRoot(['display_name'=>'good', 'monitor_idx'=>0]);
//        print_r($obj->get());
    }

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
/*
    private $testData1 =<<<EOF
[
    {
        "system": {
            "hardware_info": {
                "plc": [
                    {
                        "device_index": "1",
                        "connect_bx": [
                            {
                                "monitor_idx": "1"
                            }
                        ]
                    }
                ],
                "monitors": [
                    {
                        "monitor_idx": "1",
                        "bx_slave_id": "1",
                        "bx_device_commu_type": "1"
                    }
                ]
            },
            "main_sys": {
                "params": [
                    {
                        "display_name": "系统总电量",
                        "parameter_id": 1,
                        "monitor_idx": "1",
                        "port_name": "KWH1",
                        "device_index": "0",
                        "para_type": "5",
                        "signal_type": "107",
                        "monitoring_status": 1
                    },
                    {
                        "display_name": "室外温度",
                        "parameter_id": 2,
                        "monitor_idx": "1",
                        "port_name": "R1",
                        "device_index": "0",
                        "para_type": "1",
                        "signal_type": "103",
                        "monitoring_status": 1
                    }
                ],
                "sub_sys": [
                    {
                        "index": 1,
                        "display_name": "高温级系统(R22)",
                        "evap_sys": [
                            {
                                "index": 1,
                                "display_name": "#1蒸发系统（0.0℃蒸发温区）",
                                "params": [
                                    {
                                        "display_name": "冷间温度#1",
                                        "parameter_id": 3,
                                        "monitor_idx": "1",
                                        "port_name": "F3",
                                        "device_index": "1",
                                        "para_type": "1",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "冷间供液总开关",
                                        "parameter_id": 4,
                                        "monitor_idx": "1",
                                        "port_name": "B1",
                                        "device_index": "1",
                                        "para_type": "3",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "#1蒸发器供液开关",
                                        "parameter_id": 5,
                                        "monitor_idx": "1",
                                        "port_name": "B2",
                                        "device_index": "1",
                                        "para_type": "3",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "#2蒸发器供液开关",
                                        "parameter_id": 6,
                                        "monitor_idx": "1",
                                        "port_name": "B3",
                                        "device_index": "1",
                                        "para_type": "3",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "#1蒸发器风机开关",
                                        "parameter_id": 7,
                                        "monitor_idx": "1",
                                        "port_name": "B4",
                                        "device_index": "1",
                                        "para_type": "3",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "#2蒸发器风机开关",
                                        "parameter_id": 8,
                                        "monitor_idx": "1",
                                        "port_name": "B5",
                                        "device_index": "1",
                                        "para_type": "3",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    }
                                ]
                            }
                        ],
                        "comp_sys": [
                            {
                                "index": 1,
                                "params": [
                                    {
                                        "display_name": "吸气压力",
                                        "parameter_id": 21,
                                        "monitor_idx": "1",
                                        "port_name": "F2",
                                        "device_index": "1",
                                        "para_type": "2",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "排气压力",
                                        "parameter_id": 22,
                                        "monitor_idx": "1",
                                        "port_name": "F7",
                                        "device_index": "1",
                                        "para_type": "2",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "吸气温度",
                                        "parameter_id": 23,
                                        "monitor_idx": "1",
                                        "port_name": "F8",
                                        "device_index": "1",
                                        "para_type": "1",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "排气温度",
                                        "parameter_id": 24,
                                        "monitor_idx": "1",
                                        "port_name": "F9",
                                        "device_index": "1",
                                        "para_type": "1",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "油分供油开关",
                                        "parameter_id": 25,
                                        "monitor_idx": "1",
                                        "port_name": "B17",
                                        "device_index": "1",
                                        "para_type": "3",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "油分加热开关",
                                        "parameter_id": 26,
                                        "monitor_idx": "1",
                                        "port_name": "B18",
                                        "device_index": "1",
                                        "para_type": "3",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    }
                                ],
                                "display_name": "#1压缩系统（0.0℃蒸发温区）"
                            }
                        ],
                        "cond_sys": [
                            {
                                "index": 1,
                                "params": [
                                    {
                                        "display_name": "高压储液罐高液位报警",
                                        "parameter_id": 31,
                                        "monitor_idx": "1",
                                        "port_name": "B23",
                                        "device_index": "1",
                                        "para_type": "4",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "高压储液罐低液位报警",
                                        "parameter_id": 32,
                                        "monitor_idx": "1",
                                        "port_name": "B24",
                                        "device_index": "1",
                                        "para_type": "4",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    }
                                ],
                                "display_name": "#1冷凝系统（0.0℃蒸发温区）"
                            }
                        ]
                    },
                    {
                        "index": 1,
                        "display_name": "低温级系统(R414)",
                        "evap_sys": [
                            {
                                "index": 1,
                                "display_name": "#1蒸发系统（-10.0℃蒸发温区）",
                                "params": [
                                    {
                                        "display_name": "过冷供液温度#1",
                                        "parameter_id": 41,
                                        "monitor_idx": "1",
                                        "port_name": "F12",
                                        "device_index": "1",
                                        "para_type": "1",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "蒸发压力",
                                        "parameter_id": 42,
                                        "monitor_idx": "1",
                                        "port_name": "F13",
                                        "device_index": "1",
                                        "para_type": "2",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    }
                                ]
                            }
                        ],
                        "comp_sys": [
                            {
                                "index": 1,
                                "display_name": "#1压缩系统（-10.0℃蒸发温区）",
                                "params": [
                                    {
                                        "display_name": "吸气压力",
                                        "parameter_id": 43,
                                        "monitor_idx": "1",
                                        "port_name": "F13",
                                        "device_index": "1",
                                        "para_type": "2",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "排气压力",
                                        "parameter_id": 44,
                                        "monitor_idx": "1",
                                        "port_name": "F17",
                                        "device_index": "1",
                                        "para_type": "2",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "吸气温度",
                                        "parameter_id": 44,
                                        "monitor_idx": "1",
                                        "port_name": "F18",
                                        "device_index": "1",
                                        "para_type": "1",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "排气温度",
                                        "parameter_id": 45,
                                        "monitor_idx": "1",
                                        "port_name": "F19",
                                        "device_index": "1",
                                        "para_type": "1",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "油分供油开关",
                                        "parameter_id": 46,
                                        "monitor_idx": "1",
                                        "port_name": "B49",
                                        "device_index": "1",
                                        "para_type": "3",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "油分加热开关",
                                        "parameter_id": 47,
                                        "monitor_idx": "1",
                                        "port_name": "B50",
                                        "device_index": "1",
                                        "para_type": "3",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    }
                                ]
                            }
                        ],
                        "cond_sys": [
                            {
                                "index": 1,
                                "display_name": "#1冷凝系统（0.0℃蒸发温区）",
                                "params": [
                                    {
                                        "display_name": "高压储液罐高液位报警",
                                        "parameter_id": 48,
                                        "monitor_idx": "1",
                                        "port_name": "B55",
                                        "device_index": "1",
                                        "para_type": "4",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    },
                                    {
                                        "display_name": "高压储液罐低液位报警",
                                        "parameter_id": 49,
                                        "monitor_idx": "1",
                                        "port_name": "B56",
                                        "device_index": "1",
                                        "para_type": "4",
                                        "signal_type": "108",
                                        "monitoring_status": 1
                                    }
                                ]
                            }
                        ]
                    }
                ]
            }
        }
    }
]
EOF;
*/
}
