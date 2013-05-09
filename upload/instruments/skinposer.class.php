<?php
/*	WEB-APP : WebMCR (С) 2013 NC22 
	MODULE	: SkinPoser 1.0 (C) 2013 NC22 */
	
if (!defined('MCR')) exit;

require_once(MCR_ROOT.'instruments/skin.class.php');

Class SPItem {
private $db;
private $db_likes;
private $db_bad_skins;
private $db_ratio;

private $style;
private $base_dir;
private $base_name;

private $id;
private $name;
private $fname;
private $fsize;
private $dislikes;
private $likes;
private $ratio;
private $gender;
private $downloads;

	public function SPItem($id = false, $style = false) {
	global $bd_names, $site_ways;	
		
		$this->base_dir		= MCR_ROOT.'instruments/sp2/skins/'; 
		$this->base_url		= 'instruments/sp2/skins/';
		$this->base_name	= 'sp_nc';
		
		$this->db    		= $bd_names['skins'];
		$this->db_likes    	= $bd_names['likes'];	
		$this->db_bad_skins	= $bd_names['bad_skins'];
		$this->db_ratio		= $bd_names['skins_ratio'];
		
		$this->style 	= (!$style)? MCR_STYLE : $style;
		
		$this->id		= (!$id)? (int)$id : false; 

		$result = BD("SELECT `id`, `name`, `fname`, `fsize`, `dislikes`, `likes`, `ratio`, `gender`, `downloads`  FROM `{$this->db}` WHERE `id`='".TextBase::SQLSafe($id)."'"); 
		
		if ( mysql_num_rows( $result ) != 1 ) {
		
			$this->id = false;
			return false;	
		}
	
	    $line = mysql_fetch_array($result, MYSQL_NUM); 
		
		$this->id			= (int)$line[0];
        $this->name			= $line[1];	
		$this->fname		= $line[2];	
		$this->fsize		= $line[3];
		$this->dislikes		= (int)$line[4];
		$this->likes		= (int)$line[5];		
		$this->ratio		= (int)$line[6];		
		$this->gender		= (int)$line[7];	
		$this->downloads	= (int)$line[8];
	}
	
	public function Create($post_name, $gender = 2, $max_size = 20, $max_ratio = 1, $del_blist = false) {

		if (!POSTGood($post_name)) return 1;
		
		$max_size = (int) $max_size;
		$max_ratio = (int) $max_ratio;
		$gender = (int)$gender;
		if ($gender > 2 or $gender < 0) $gender = 2;
		
		$new_file_info = POSTSafeMove($post_name, $this->base_dir); if (!$new_file_info) return 2;
		
		$way  = $this->base_dir.$new_file_info['tmp_name'];		
		$hash = md5_file($this->base_dir.$new_file_info['tmp_name']);
		
		if ($del_blist) BD("DELETE FROM {$this->db_bad_skins} WHERE hash='".$hash."'");
		
		$result = BD("SELECT `id`, 'good_skin' AS `type` FROM `{$this->db}` WHERE hash='".$hash."' UNION SELECT `id`, 'bad_skin' AS `type` FROM {$this->db_bad_skins} WHERE `hash`='".$hash."'");
		
		if (mysql_num_rows( $result )) {
			
			$line = mysql_fetch_array( $result );
			
			unlink($way);
			
			if ($line['type'] == 'bad_skin') return 3;
			else                             return $line['id'] * -1;	
		}
		
		if ( $max_size < $new_file_info['size_mb'] * 1024 )	{ 
		
			unlink($way); 
			return 4; 
		}
		
		$new_file_ratio = skinGenerator2D::isValidSkin($way); 
		if (!$new_file_ratio or $new_file_ratio > $max_ratio) {

			unlink($way);
			return 5; 
		}
		
		BD("INSERT INTO `{$this->db}` (hash, fsize, ratio, gender) VALUES ('".$hash."','".$new_file_info['size_mb']."','".$new_file_ratio."', '".$gender."')");
		
		$this->id = mysql_insert_id();
		$new_name = 'sp_nc'.$this->id.'.png';
		$new_way = $this->base_dir.$new_name;
		
		BD("UPDATE `{$this->db}` SET `fname` = '".$new_name."' WHERE `id`='".$this->id."'");
		
		if (file_exists($new_way)) unlink($new_way);
		
		if (rename( $way, $new_way )) chmod($new_way , 0777);
		else { unlink($way); BD("DELETE FROM `{$this->db}` WHERE `id`='".$this->id."'"); return 6; }	
		
		if (!skinGenerator2D::savePreview($this->base_dir.'preview/'.$new_name, $new_way, false, false, 160))
		{ unlink($new_way); BD("DELETE FROM `{$this->db}` WHERE `id`='".$this->id."'"); return 7; }	
		
		BD("LOCK TABLES `{$this->db_ratio}` WRITE;");
		BD("INSERT INTO `{$this->db_ratio}` (ratio) VALUES ('".((int)$new_file_ratio)."') ON DUPLICATE KEY UPDATE `num`= num + 1;");
		BD("UNLOCK TABLES;");	

        $this->name			= '';	
		$this->fname		= $new_name;	
		$this->fsize		= $new_file_info['size_mb'];
		$this->dislikes		= 0;
		$this->likes		= 0;		
		$this->ratio		= $new_file_ratio;		
		$this->gender		= $gender;	
		$this->downloads	= 0;		
		
	return 0; 
	}
	
	public function Rebuild() {
	
		$skin_way = $this->base_dir.$this->fname;
		$preview_way = $this->base_dir.'preview/'.$this->fname;
		
		if (!file_exists($skin_way)) {
		
			$this->Delete();
			vtxtlog('[Rebuild][skinGenerator2D] SPItem ID '.$this->id.' not founded - delete');
			
			return false;
		}
		
		if (file_exists($preview_way)) unlink($preview_way);
		
		$skin_ratio = skinGenerator2D::isValidSkin($skin_way); 
		if (!$skin_ratio) {
		
			$this->Delete();
			vtxtlog('[Rebuild][skinGenerator2D] SPItem ID '.$this->id.' wrong skin format - delete');
		}
		
		if (!skinGenerator2D::savePreview($preview_way, $skin_way, false, false, 160) or !file_exists($preview_way))
		
		vtxtlog('[Rebuild][skinGenerator2D] Fail rebuild preview for SPItem ID '.$this->id);
				
		BD("LOCK TABLES `{$this->db_ratio}` WRITE;");
		BD("INSERT INTO `{$this->db_ratio}` (ratio) VALUES ('".((int)$skin_ratio)."') ON DUPLICATE KEY UPDATE `num`= num + 1;");
		BD("UNLOCK TABLES;");

		if ($this->ratio != $skin_ratio) {
		
			BD("UPDATE `{$this->db}` SET `ratio` = '".$skin_ratio."' WHERE `id`='".$this->id."'");
			$this->ratio = $skin_ratio;
		}
	}
	
	public function ApplayToUser($user_id) {
	global $bd_users;
	
	if (!$this->Exist()) return false;
	
	$work_user = new User($user_id, $bd_users['id']);
	if (!$work_user->id()) return false;
	
	$female = $work_user->isFemale();
	if (($this->isFemaleSkin() and !$female) or (!$this->isFemaleSkin() and $female)) 
	return false;
	
	if ($work_user->getPermission('max_ratio') < $this->ratio) return false;
	
	$work_user->deleteSkin();		
	$work_user->deleteBuffer();
		
	$user_skin_way = $work_user->getSkinFName(); 
		
	if (copy($this->base_dir.$this->fname, $user_skin_way)) chmod($user_skin_way , 0777);
	else return false;
	
	if ( !strcmp($work_user->defaultSkinMD5(), md5_file($work_user->getSkinFName())) ) 
		$work_user->defaultSkinTrigger(true);
	else
		$work_user->defaultSkinTrigger(false);	
		
	return true;		
	}
	
	public function isFemaleSkin() {
	if (!$this->Exist()) return false;
	
	if ($this->gender == 1) return true;
	return false;	
	}
	
	public function Like($dislike = false) {
	global $user;
	
		if (!$this->Exist() or empty($user) or !$user->lvl()) return 0;		
		
        $like = new ItemLike(ItemType::Skin, $this->id, $user->id());

		return $like->Like($dislike);
	}
	
	public function Exist() {
		if ($this->id) return true;
		return false;
	}
	
	public function getInfo() {
		if (!$this->Exist()) return false; 
		
		return array (	'id' 		=> $this->id,
						'name'		=> $this->fname,
						'size'		=> $this->fsize,
						'dislikes'	=> $this->dislikes,
						'likes'		=> $this->likes,
						'downloads'	=> $this->downloads,
						'gender'	=> $this->gender,
						'ratio'		=> $this->ratio );		
	}	
	
	public function Show() {
	global $config, $user;
	
	if (!$this->Exist()) return '';

	$available = true;
	$admin = false;
	
	if (!empty($user)) {
		
		$female = $user->isFemale();
		if (($this->isFemaleSkin() and !$female) or (!$this->isFemaleSkin() and $female)) 
		
		$available = false;
		
		if ($user->group() == 3) $admin = true;
		
		if ($user->getPermission('max_ratio') < $this->ratio) $available = false;
	} else $available = false;

		$skin_info = $this->getInfo();
		
		$skin_id 	= $skin_info['id'];
		$skin_likes	= $skin_info['likes'];
		
		$skin_dislikes	= $skin_info['dislikes'];
		
		$skin_name	= $skin_info['name'];
		$skin_size	= $skin_info['size'];
		
		if ($skin_size <= 0) $skin_size = '< 0.01';
		
		$skin_ratio	= (64*$skin_info['ratio']).'x'.(32*$skin_info['ratio']);
		
		$skin_gender = $skin_info['gender'];		
		
		$skin_downloads	= $skin_info['downloads'];
		
		$skin_preview = $this->base_url.'preview/'.$this->base_name.$skin_id.'.png';
		
		ob_start(); include $this->style.'skinposer/skin.html';
		
	return ob_get_clean();
	}
	
	public function Delete() {
	global $bd_names;
	
		if (!$this->Exist()) return false;
		
		$skin_way = $this->base_dir.$this->fname;
		$preview_way = $this->base_dir.'preview/'.$this->fname;
		
		if (file_exists($skin_way)) unlink($skin_way);
		if (file_exists($preview_way)) unlink($preview_way);
		
		BD("DELETE FROM `{$this->db}` WHERE `id`='".$this->id."'");	
		BD("DELETE FROM `{$this->db_likes} ` WHERE `item_id`='".$this->id."' AND `item_type`='".ItemType::Skin."'");
		
		BD("LOCK TABLES `{$this->db_ratio}` WRITE;");
		BD("UPDATE `{$this->db_ratio}` SET `num` = num - 1 WHERE `ratio`='".$this->ratio."'");
		BD("UNLOCK TABLES;");
		
		$this->id = false;
	
	return true; 
	}
}

Class SkinMenager extends Menager {
private $style;
private $base_url;
private $url_params;
private $db;
private $db_ratio;

    public function SkinMenager($style = false, $base_url = 'index.php?mode=skinposer', $url_params = false) {
	global $bd_names;	
	
		$this->db			= $bd_names['skins'];
		$this->db_ratio		= $bd_names['skins_ratio'];
		$this->type			= ItemType::Skin;
		$this->db_likes		= $bd_names['likes'];
		$this->style		= (!$style)? MCR_STYLE : $style;		
		$this->base_url		= $base_url;
		$this->url_params	= (!$url_params)? '' : $url_params;	
		
		parent::Menager($this->style);		
	}
	
	public function ShowSortTypeSelector($order_by = 'id', $sort = 1) {

		ob_start(); 
		include $this->style.'skinposer/sort.html'; 
	
	return ob_get_clean();		
	}	
	
	public function RebuildAll() {
	
	BD("LOCK TABLES `{$this->db_ratio}` WRITE;");
	BD("DELETE FROM `{$this->db_ratio}` WHERE `ratio` != '0'");	
	BD("UNLOCK TABLES;");
	
		$result = BD("SELECT `id` FROM `".$this->db."`");
			
			if ( mysql_num_rows( $result ))
			
				while ( $line = mysql_fetch_array( $result, MYSQL_NUM ) ) {
					
					$skin = new SPItem($line[0]);				
					$skin->Rebuild();					
				}	

	}
	
	public function ShowGenderSelector($current = 4) {
		
		$html = '<option value="0" '.(($current == 0)? 'selected' : '').'>Без тега + Мужские</option>';
		$html .= '<option value="1" '.(($current == 1)? 'selected' : '').'>Без тега + Женские</option>';
		$html .= '<option value="2" '.(($current == 2)? 'selected' : '').'>Без тега</option>';
		$html .= '<option value="3" '.(($current == 3)? 'selected' : '').'>Мужские</option>';
		$html .= '<option value="4" '.(($current == 4)? 'selected' : '').'>Женские</option>';

		return $html;
	}
	
	public function ShowSideMenu($gender = 0, $ratio = 1) {
	global $user;
			
	$gender_list = $this->ShowGenderSelector($gender);
	$ratio_list = '';
	$admin = false;

	if (!empty($user) and $user->group() == 3) $admin = true;
				
	$result = BD("SELECT `ratio`,`num` FROM `".$this->db_ratio."` ORDER BY `ratio` LIMIT 0, 90");
	
	while ( $line = mysql_fetch_array( $result, MYSQL_NUM ) ) 
	
			$ratio_list .= '<option value="'.$line[0].'" '.(($line[0] == $ratio)?'selected':'').'>'.(64*$line[0]).'x'.(32*$line[0]).' ('.$line[1].')</option>';
			
		ob_start(); 
		include $this->style.'skinposer/ratio.html'; 
	
	return ob_get_clean();		
	}
	
	public function ShowAddForm(){		
	global $user;
	
	if (empty($user)) exit;
	
	$max_ratio = $user->getPermission('max_ratio');
	$max_ratio_text = (62*$max_ratio).'x'.(32*$max_ratio);
	
	$max_fsize = $user->getPermission('max_fsize');	
	
	ob_start(); 
	include $this->style.'skinposer/add_skin_form.html'; 
	
	return ob_get_clean();
	}
	
	public function ShowSkinList($list = 1, $per_page = 20, $gender = 2, $order_by = 'id', $sort = 1, $mode = 'base', $ratio = 1) {
	global $user;		
		
			$list = (int) $list;
		if ($list <= 0) $list = 1; 
			$ratio = (int) $ratio;
		if (($ratio < 1 or $ratio > 64) and $ratio != 1337 and $ratio != 31337) return '';		
		
		$order_by 	= ($order_by == 'id')? 'id' : 'likes';
		$sort		= ($sort == 2)? 'DESC' : '';	
	
		// echo '['.$list.' |'.$per_page.' |'.$gender.' |'.$order_by.' |'.$sort.' |'.$mode.' |'.$ratio.']';
		
		$mode_txt = 'Классические образы (64х32)';
		$gender_txt = 'Женские';	
		
			if ($gender < 2)  {
			
			$gender_txt = (($gender)? 'Женские' : 'Мужские').' и без тега';
			$base_sql = "WHERE (`gender` = '2' OR `gender` = '".(($gender)? 1 : 0)."')";
			
		} elseif ($gender == 2) {
		
			$gender_txt = 'Без тега';
			$base_sql = "WHERE `gender` = '2'";		
			
		} elseif ($gender == 3) {
		
			$gender_txt = 'Мужские';
			$base_sql = "WHERE `gender` = '0'";	
			
		} else	$base_sql = "WHERE `gender` = '1'";	
		
			if ($mode == 'HD') $mode_txt = 'Высокое разрешение';
		elseif ($mode == 'likes') {
			
			$gender_txt = 'Любые';
			$mode_txt = 'Понравившиеся';	
		}		
		
		if ($mode != 'like') {
		
			if ($mode == 'HD' and $ratio == 1337) $base_sql .= " AND `ratio` != '1'";
			else $base_sql .= " AND `ratio` = '".$ratio."'";

			$skins_count = mysql_result(BD("SELECT COUNT(*) FROM `".$this->db."` ".$base_sql), 0);
			
		} else {
		
			if (empty($user)) $skins_count = 0;
			
			else
			
			$skins_count = mysql_result(BD("SELECT COUNT(*) FROM `".$this->db_likes."` WHERE `user_id`= '".$user->id()."' AND `item_type` = '".$this->type."' AND `var`='1'"), 0);
		}
		
		$html_skin_list = '';

		if ($list > ceil($skins_count / $per_page)) $list = 1;
		
		if (!$skins_count) $html_skin_list = Menager::ShowStaticPage($this->style.'skinposer/skin_empty.html');
		else {
		
			if ($mode != 'like')
			
			$result = BD("SELECT `id` FROM `".$this->db."` ".$base_sql." ORDER BY `$order_by` $sort LIMIT ".($per_page*($list-1)).",$per_page");
			
			else {

			$sql = 	"SELECT `skin_id` AS 'id'";
			$sql .= "FROM `".$this->db."` LEFT JOIN `".$this->db_likes."` ON ".$this->db.".id = ".$this->db_likes.".skin_id";
			$sql .= "WHERE ".$this->db_likes.".user_id = '".$user->id()."' AND `item_type` = '".$this->type."' AND `var` = '1' ORDER BY ".$this->db_likes.".id DESC LIMIT ".($per_page*($list-1)).",$per_page";
			
			$result = BD($sql);			
			}	

			if ( !mysql_num_rows( $result )) $html_skin_list = Menager::ShowStaticPage($this->style.'skinposer/skin_empty.html');

			while ( $line = mysql_fetch_array( $result, MYSQL_NUM ) ) {
			
				$skin = new SPItem($line[0]);				
				$html_skin_list .= $skin->Show();
			}
		}
		
		$html_skin_list .= $this->arrowsGenerator($this->base_url.$this->url_params.'&', $list, $skins_count, $per_page, 'other/common');
		
		ob_start(); 
		include $this->style.'skinposer/main.html'; 
	
	return ob_get_clean();
	}
}