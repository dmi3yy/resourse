<?php
/**
version: 0.3.0

Author:
	* Bumkaka from modx.im
	* Agel_Nash <agel_nash@xaker.ru>

USE:
require_once('assets/libs/resourse.php');
$resourse=resourse::Instance($modx);

#------------------------------------------------------
* Add new document without invoke event and clear cache
$resourse->document()->set('titl','Пропаганда')->set('pagetitle',$i)->save(null,false);

* Add new document without invoke event and call clear cache
$resourse->document()->set('titl','Пропаганда')->set('pagetitle',$i)->save(null,true);

* Add new document call event and without clear cache
$resourse->document()->set('titl','Пропаганда')->set('pagetitle',$i)->save(true,false);

#-------------------------------------------------------
#Edit resourse #13 
$resourse->edit(13)->set('pagetitle','new pagetitle')->save(null,false);

#-------------------------------------------------------
$resourse->delete(8);

*/
if(!defined('MODX_BASE_PATH')) {die('What are you doing? Get out of here!');}


class resourse {
	static $_instance = null;
	private $_modx = null;
	private $id = 0;
	private $field = array();
	private $tv = array();
	private $tvid = array();
	private $log = array();
	private $new_resourse = 0;
	private $dafeult_field ;
	private $table=array('"'=>'_',"'"=>'_',' '=>'_','.'=>'_',','=>'_','а'=>'a','б'=>'b','в'=>'v',
		'г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k',
		'л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
		'ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ь'=>'','ы'=>'y','ъ'=>'',
		'э'=>'e','ю'=>'yu','я'=>'ya','А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E',
		'Ё'=>'E','Ж'=>'Zh','З'=>'Z','И'=>'I','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N',
		'О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'H','Ц'=>'C',
		'Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Sch','Ь'=>'','Ы'=>'Y','Ъ'=>'','Э'=>'E','Ю'=>'Yu','Я'=>'Ya',
	);

	private $set;	
	private $flag = false;
	private $_table = array('site_content','site_tmplvar_contentvalues','site_tmplvars');
	
	private function __construct($modx){
		try{
			if($modx instanceof DocumentParser){
				$this->modx = $modx;
			} else throw new Exception('MODX should be instance of DocumentParser');
			
			if(!$this->makeTable()) throw new Exception('Not exists table');
			
		}catch(Exception $e){ die($e->getMessage()); }
		
		$this->get_TV();
	}
	
	private final function __clone(){throw new Exception('Clone is not allowed');}
	
	static public function Instance($modx){
		if (self::$_instance == NULL){self::$_instance = new self($modx);}
		return self::$_instance;
	}
	
	public function document($id=0){
		$this->new_resourse = $id == 0;
		var_dump($this->new_resourse);
		$this->id = $id;
		$this->field=array();
		$this->set=array();
		$this->default_field = array('pagetitle'=>'New document','alias'=>'','parent'=>'0','createdon'=>time(),'createdby'=>'0','editedon'=>'0','editedby'=>'0','published'=>'1','deleted'=>'0','hidemenu'=>'1','template'=>'0','content'=>'');
		$this->flag = true;
		return $this;
	}
	
	private function makeTable(){
		//@TODO: check exists table
		$flag = true;
		foreach($this->_table as $item){
			$this->_table[$item] = $this->modx->getFullTableName($item);
		}
		return $flag;
	}
	
	private function Uset($key){
		if(!isset($this->field[$key])){ 
			$this->set[]= "{$key}=''";
			$this->log[] =  '{$key} is empty';
		} else {
			try{
				if(is_scalar($this->field[$key])){
					$this->set[]= "{$key}='{$this->field[$key]}'";
				} else throw new Exception("{$key} is not scalar <pre>".print_r($this->field[$key],true)."</pre>");
			}catch(Exception $e){ die($e->getMessage()); }
		}
		return $this;
	}
	
	private function invokeEvent($name,$data=array(),$flag=false){
		$flag = (isset($flag) && $flag!='') ? (bool)$flag : false;
		if($flag){
			$this->modx->invokeEvent($name,$data);
		}
		return $this;
	}
	
	public function clear_chache($fire_events = null){
		$this->modx->clearCache();
		include_once (MODX_MANAGER_PATH . '/processors/cache_sync.class.processor.php');
		$sync = new synccache();
		$sync->setCachepath(MODX_BASE_PATH . "assets/cache/");
		$sync->setReport(false);
		$sync->emptyCache();
		$this->invokeEvent('OnSiteRefresh',array(),$fire_events);
	}
	
	public function list_log($flush = false){
		echo '<pre>'.print_r($this->log,true).'</pre>';
		if($flush) $this->clearLog();
		return $this;
	}
	
	public function clearLog(){
		$this->log = array();
		return $this;
	}
	
	public function set($key,$value){
		if(is_scalar($value) && is_scalar($key) && !empty($key)){
			$this->field[$key] = $value;
		}
		return $this;
	}
	
	public function get($key){
		return isset($this->field[$key]) ? $this->field[$key] : null;
	}
	
	private function getAlias(){
		if ($this->modx->config['friendly_urls'] && $this->modx->config['automatic_alias'] && $this->get('alias') == ''){
			$alias = strtr($this->get('pagetitle'), $this->table);
		}else{
			if($this->get('alias')!=''){
				$alias = $this->get('alias');
			}
		}
		return $this->checkAlias($alias);
	}
	
	private function query($SQL){
		return $this->modx->db->query($SQL);
	}
	
	public function get_TV(){
		$result = $this->query('SELECT id,name FROM '.$this->_table['site_tmplvars']);
		while($row = $this->modx->db->GetRow($result)) {
			$this->tv[$row['name']] = $row['id'];
			$this->tvid[$row['id']] = $row['name'];
		}
	}
	
	public function fromArray($data){
		foreach($data as $key=>$value) $this->set($key,$value);
		return $this;
	}
	
	public function edit($id){
		if(!$this->flag) $this->document($id);
		
		$result = $this->query("SELECT * from {$this->_table['site_content']} where id=".(int)$id);
		$this->fromArray($this->modx->db->getRow($result));

		$result = $this->query("SELECT * from {$this->_table['site_tmplvar_contentvalues']} where contentid=".(int)$id);
		while ($row = $this->modx->db->getRow($result)){
			$this->set($this->tvid[$row['tmplvarid']], $row['value']);
		}
		unset($this->field['id']);
		return $this;
	}
	
	public function delete($id,$fire_events = null){
		if(is_scalar($id)){
			$id = array($id);
		}
		foreach($id as $i){
			if((int)$i>0){
				$this->query("DELETE from {$this->_table['site_content']} where id=".(int)$i);
				$this->query("DELETE from {$this->_table['site_tmplvar_contentvalues']} where contentid=".(int)$i);
				//@TODO: $this->invokeEvent('On..........',array(),$fire_events);
			}
		}
		return $this;
	}
	
	public function toArray(){
		return $this->field;
	}
	private function checkAlias($alias){
		if($this->modx->config['friendly_urls']){
			$flag = false;
			$_alias = $this->modx->db->escape($alias);
			if(!$this->modx->config['allow_duplicate_alias'] || ($this->modx->config['allow_duplicate_alias'] && $this->modx->conifg['use_alias_path'])){
				$flag = $this->modx->db->getValue("SELECT id FROM {$this->_table['site_content']} WHERE alias='{$_alias}' AND parent={$this->get('parent')} LIMIT 1");
			} else {
				$flag = $this->modx->db->getValue("SELECT id FROM {$this->_table['site_content']} WHERE alias='{$_alias}' LIMIT 1");
			}
			
			if($flag){
				$suffix = substr($alias, -1);
				if(preg_match('/\d+/',$suffix) && (int)$suffix>1){
					(int)$suffix++;
					$alias = substr($alias, 0, -1) . $suffix;
				}else{
					$alias .= '2';
				}
				$alias = $this->checkAlias($alias);
			}
		}
		return $alias;
	}
	
	public function save($fire_events = null,$clearCache = false){
		try{
			if(!$this->flag){
				throw new Exception('You need flush document field before set and save resource');
			}
		}catch(Exception $e){ die($e->getMessage()); }
		
		if ($this->field['pagetitle'] == '') {
			$this->log[] =  'Pagetitle is empty in <pre>'.print_r($this->field,true).'</pre>';
			return false;
		}
		$this->set('alias',$this->getAlias());

		$fld = $this->toArray();
		foreach($this->default_field as $key=>$value){
			if ($this->new_resourse && $this->get($key) == '' && $this->get($key)!==$value){
				$this->set($key,$value)->Uset($key);
			} else {
				$this->Uset($key);
			}
			unset($fld[$key]);
		}
		if (!empty($this->set)){
			if($this->new_resourse){
				$SQL = "INSERT into {$this->_table['site_content']} SET ".implode(', ', $this->set);
			}else{
				$SQL = "UPDATE {$this->_table['site_content']} SET ".implode(', ', $this->set)." WHERE id = ".$this->id;
			}
			$this->query($SQL);
		}
		
		if($this->new_resourse) $this->id = $this->modx->db->getInsertId();
		
		foreach($fld as $key=>$value){
			if ($value=='') continue;
 			if ($this->tv[$key]!=''){
				$result = $this->query("UPDATE {$this->_table['site_tmplvar_contentvalues']} SET `value` = '{$value}' WHERE `contentid` = '{$this->id}' AND `tmplvarid` = '{$this->tv[$key]}';");
				$rc = mysql_affected_rows();
				if ($rc==0){
					$result = $this->query("INSERT into {$this->_table['site_tmplvar_contentvalues']} SET `contentid` = {$this->id},`tmplvarid` = {$this->tv[$key]},`value` = '{$value}';");
				}
			}
		}
		$this->invokeEvent('OnDocFormSave',array(),$fire_events);
		if($clearCache){ 
			$this->clear_chache($fire_events); 
		}
		$this->flag = false;
		return $this->id;
	}
}