<?php

if(PHP_SAPI !== 'cli' || !isset($argv[1],$argv[2],$argv[3])) exit(1);
$work=realpath($argv[2]);
if(!$work || !str_starts_with($work,'/tmp/dewawi-technical-test-') || $argv[1]!==$work.'/mysql.sock') exit(1);
$root=dirname(__DIR__,3);
error_reporting(E_ALL & ~E_DEPRECATED);
set_include_path($root.'/library'.PATH_SEPARATOR.get_include_path());
spl_autoload_register(static function(string $class) use($root):void {
	if($file=stream_resolve_include_path(str_replace('_','/',$class).'.php')) {require_once $file;return;}
	$parts=explode('_',$class);if(count($parts)<3)return;
	$module=array_shift($parts);$section=strtolower(array_shift($parts)).'s';
	$file=$root.($module==='Application'?'/application/':'/application/modules/'.strtolower($module).'/').$section.'/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;
});
try {
	$db=Zend_Db::factory('Pdo_Mysql',['host'=>'localhost','unix_socket'=>$argv[1],'username'=>'root','password'=>'','dbname'=>'technical_fresh','charset'=>'utf8']);
	Zend_Db_Table::setDefaultAdapter($db);
	Zend_Registry::set('User',$db->fetchRow('SELECT * FROM user WHERE id=10'));
	Zend_Registry::set('Client',$db->fetchRow('SELECT * FROM client WHERE id=100'));
	Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());Zend_Auth::getInstance()->getStorage()->write((object)['id'=>10]);
	$service=new Items_Service_TechnicalDataset();
	if($argv[3]==='import') $result=$service->importJson(file_get_contents($work.'/race.json'));
	elseif($argv[3]==='approve') { $target=json_decode(file_get_contents($work.'/race-approval.json'),true,32,JSON_THROW_ON_ERROR);$service->approve('technicaldataset',$target['id'],$target['hash'],$target['evidence']);$result=['approved'=>true]; }
	else throw new InvalidArgumentException('Invalid race mode');
	echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n");exit(1); }
