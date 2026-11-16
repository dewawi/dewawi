<?php

if(PHP_SAPI !== 'cli' || !isset($argv[1],$argv[2]) || !preg_match('#^(/tmp/dewawi-technical-test-[^/]+)/mysql\.sock$#D',$argv[1],$match) || !preg_match('#^'.preg_quote($match[1],'#').'/review-worker-[a-f0-9]+\.bin$#D',$argv[2])) exit(1);
$root=dirname(__DIR__,2);$work=$match[1];error_reporting(E_ALL & ~E_DEPRECATED);
set_include_path($root.'/library'.PATH_SEPARATOR.get_include_path());
spl_autoload_register(static function(string $class)use($root):void{if($file=stream_resolve_include_path(str_replace('_','/',$class).'.php')){require_once $file;return;}$parts=explode('_',$class);if(count($parts)<3)return;$module=array_shift($parts);$section=strtolower(array_shift($parts)).'s';$file=$root.($module==='Application'?'/application/':'/application/modules/'.strtolower($module).'/').$section.'/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;});
Zend_Session::start(['save_path'=>$work]);
$db=Zend_Db::factory('Pdo_Mysql',['host'=>'localhost','unix_socket'=>$argv[1],'username'=>'root','password'=>'','dbname'=>'technical_fresh','charset'=>'utf8']);Zend_Db_Table::setDefaultAdapter($db);
Zend_Registry::set('User',$db->fetchRow('SELECT * FROM user WHERE id=10'));Zend_Registry::set('Client',$db->fetchRow('SELECT * FROM client WHERE id=100'));
Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());Zend_Auth::getInstance()->getStorage()->write((object)['id'=>10]);
$job=unserialize(file_get_contents($argv[2]),['allowed_classes'=>['stdClass']]);$session=new Zend_Session_Namespace('InquiryTechnicalReview');$session->pending=[$job['token']=>$job['receipt']];
putenv('DEWAWI_SELECTION_INTERNAL_CLIENTS=100');
for($i=0;$i<200&&!is_file($work.'/review-start');$i++)usleep(10000);
echo (new Contacts_Service_InquiryTechnicalReview())->save(918,$job['token']);
