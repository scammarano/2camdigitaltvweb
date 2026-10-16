<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/device_config.php';
require dirname(__DIR__).'/app/mode_runtime.php';
function expect(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function rejects(callable $f):void{try{$f();}catch(RuntimeException $e){return;}throw new RuntimeException('Invalid configuration accepted');}
$v=ds_config_defaults();expect(ds_config_validate($v)===$v,'Default roundtrip');
expect(ds_config_validate(['update_channel'=>'beta'])['update_channel']==='stable','Legacy beta becomes stable-only');
$channel=array_values(array_filter(ds_config_schema()['fields'],static fn($f)=>$f['key']==='update_channel'))[0];
expect($channel['options']===['stable'=>'Solo estables','debug'=>'Estables y pruebas'],'Exactly two unified channel options');
foreach([1,2,3,4] as $mode){$v['primary_mode']=$mode;expect(ds_config_validate($v)['primary_mode']===$mode,'Four modes');}
$v['enabled_modes']=7;rejects(fn()=>ds_config_validate($v));$v=ds_config_defaults();
$v['overlay_opacity']=101;rejects(fn()=>ds_config_validate($v));$v=ds_config_defaults();
$v['overlay_duration_sec']=$v['overlay_interval_sec'];rejects(fn()=>ds_config_validate($v));
$old=ds_config_validate(['primary_mode'=>3,'smart_app'=>'scoreboard','smart_header_pct'=>40,'enabled_modes'=>7]);
expect($old['primary_mode']===4 && $old['enabled_modes']===15,'Legacy scoreboard migration');
$now=new DateTimeImmutable('2026-10-16T12:00:00Z');
$block=['mode'=>4,'days_mask'=>127,'start_time'=>'00:00:00','end_time'=>'23:59:59','valid_from'=>null,'valid_until'=>null];
expect(resolve_mode(['mode'=>1],[$block],$now,15)===4,'Scheduled scoreboard');
expect(resolve_mode(['mode'=>1],[$block],$now,7)===1,'Disabled mode restricted');
echo 'CONFIG_MODES_PASS: validation, migration, four modes and schedule restrictions'.PHP_EOL;
