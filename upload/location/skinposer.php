<?php if (!defined('MCR')) exit;
 
if ((!empty($user) and $user->lvl() <= 0) or 
	(isset($config['sp_online']) and !$config['sp_online'] and 
	((!empty($user) and $user->group() != 3) or empty($user)))) { 

	header("Location: ".BASE_URL); exit; 
}

require(MCR_ROOT.'instruments/skinposer.class.php');

/* Action */

$do = false;

if ((!empty($_POST['do']) or !empty($_GET['do'])) and !empty($user)) {
	
	require(MCR_ROOT.'instruments/ajax.php');	

	$do = (isset($_POST['do']))? $_POST['do'] : $_GET['do'];

	switch ($do) {
		case 'admin': /* skiped; show part is on bottom */ break;
		case 'del':
		
			if (empty($_POST['skin_id'])) aExit(1);	
			
			$skin_id = (int) $_POST['skin_id'];
			$sp_item = new SPItem($skin_id);
			
			if ($sp_item->Delete()) aExit(0);
			else aExit(2);
			 
		break;	
		case 'get':
		
			if (empty($_POST['skin_id'])) aExit(1);	
			CaptchaCheck(2);			 
			
			$skin_id = (int) $_POST['skin_id'];
			$sp_item = new SPItem($skin_id);
			
			if ($user->isFemale() and !$sp_item->isFemaleSkin()) 
			
			aExit(3, 'Этот скин подходит только для персонажей мужского пола');
			
			elseif (!$user->isFemale() and $sp_item->isFemaleSkin()) 
			
			aExit(5, 'Этот скин подходит только для персонажей женского пола');
			
			if ($sp_item->ApplayToUser($user->id())) aExit(0);
			else aExit(4);
			 
		break;	
		case 'add':	
		
			if (!$user->getPermission('change_skin') or !$config['sp_upload']) { exit; break; }
				
			$max_ratio 		= $user->getPermission('max_ratio');
			$max_fsize 		= $user->getPermission('max_fsize');
			
			$max_ratio_text = (62*$max_ratio).'x'.(32*$max_ratio);		
			
			$skin_gender	= (!isset($_POST['skin_gender']))? 2 : (int) $_POST['skin_gender'];
			$skin_check		= (empty($_POST['skin_check']))? false : true; 	
		
			$sp_item = new SPItem();
			$result = $sp_item->Create('skin_upload', $skin_gender, $max_fsize, $max_ratio, $skin_check);
			$error = '';
			
			if ($result < 0) $error = 'Скин уже есть в базе. <a href="index.php?mode=skins&id='.($result * -1).'">Перейти</a>';
			else
			
			switch($result) {
				case 1: $error = 'Ошибка при загрузке файла. (Допустимый формат файла - png)'; break;
				case 3: $error = 'Скин занесен в блек-лист.'; break;
				case 4: $error = 'Размер файла превышает предельно допустимый. ('.$max_fsize.'кб)'; break;
				case 5: $error = 'Размеры изображения заданы неверно. (Рекомендуемое разрешение '.$max_ratio_text.')';  break;
				case 2: case 6: $error = 'Ошибка добавления файла. Включите лог для просмотра подробной информации.'; break;
				case 7: $error = 'Ошибка при сохранении изображения предварительного просмотра.'; break;
			}

			if ($result == 0 ) { 
			
				$skin_info = $sp_item->getInfo();
				$ajax_message['gender'] = (int) $skin_info['gender'];
				$ajax_message['ratio'] = (int) $skin_info['ratio'];
			}
			
			aExit($result, $error);			
			
		break;
		default: exit; break;
	}
}

/* Default vars */

if ($do and $do == 'admin') 
	$menu->SetItemActive('sp_admin');
else
	$menu->SetItemActive('skinposer');

$page    = 'Галерея образов';

$curlist = (isset($_GET['l'])) ? (int) $_GET['l'] : 1;
if ( $curlist <= 0 ) $curlist = 1;

$ratio   = (isset($_GET['ratio'])) ? (int) $_GET['ratio'] : 1;	

$mode    = 'base';

	if ($ratio == 31337) 	$mode = 'likes';
elseif ($ratio > 1)			$mode = 'HD';

$gender_base = (!empty($user) and $user->isFemale())? 1 : 0;

if (!empty($_GET['type'])) $gender = (int) $_GET['type'];
else $gender = $gender_base;

$work_url = 'index.php?mode=skinposer'; $url_params = '';

if ( $ratio > 1 ) $url_params .= '&ratio='.$ratio;
if ( $gender != $gender_base ) $url_params .= '&type='.$gender;  

if (!isset($_SESSION['show_mode']))    $_SESSION['show_mode'] = 2; 
if (!isset($_SESSION['method_mode']))  $_SESSION['method_mode'] = 2; 
if (!isset($_SESSION['num_per_page'])) $_SESSION['num_per_page'] = 24; 

if (!empty($_GET['sort']))            $_SESSION['show_mode'] 	= (int) $_GET['sort'];
if (!empty($_GET['method']))	      $_SESSION['method_mode']  = (int) $_GET['method'];
if (!empty($_GET['skins_per_page']))  $_SESSION['num_per_page'] = (int) $_GET['skins_per_page'];

if ( $_SESSION['num_per_page'] > 100 ) $_SESSION['num_per_page'] = 100;
if ( $_SESSION['num_per_page'] < 5 )   $_SESSION['num_per_page'] = 5;

$skin_manager = new SkinMenager(false, $work_url, $url_params);

/* Show */

$conf_info = $skin_manager->TryAutoConfigure();

if ($conf_info) {

	$content_main .= $conf_info; 
	
} elseif ($do and $do == 'admin') {

	$content_main .= $skin_manager->ShowAdminForm();
	
} else {
	
	if (!empty($user) and $user->getPermission('change_skin')) 
		
		$content_main .= $skin_manager->ShowAddForm();
		
	if (!$config['sp_online']) $content_main .=  $skin_manager->ShowSPCloseInfo();
	
	$order_by = ($_SESSION['method_mode'] == 1)? 'id' : 'likes';
		
	$content_main .= $skin_manager->ShowSkinList($curlist, $_SESSION['num_per_page'], $gender, $order_by, $_SESSION['show_mode'], $mode, $ratio);

	$content_main .= $skin_manager->ShowSortTypeSelector($order_by, $_SESSION['show_mode']);	
	$content_side .= $skin_manager->ShowSideMenu($gender, $ratio);
}
